using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

/// <summary>
/// Applies the per-call connection (base URL, bearer token) to ShipX requests and converts failures - a non-2xx
/// response or a 2xx in-band error envelope - into <see cref="InPostApiException"/>. Retrying is done by the
/// resilience handler registered in front of this one.
/// </summary>
public class InPostConnectionHandler : DelegatingHandler
{
    public const string ContextOptionKey = "InPost.ConnectionContext";

    private static readonly HttpRequestOptionsKey<object?> _contextKey = new(ContextOptionKey);

    protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        if (!request.Options.TryGetValue(_contextKey, out var value) || value is not ShippingProviderConnectionContext context)
        {
            throw new InvalidOperationException("The InPost request has no connection context.");
        }

        // The pipeline may resend the same message, so these assignments are idempotent.
        request.RequestUri = new Uri(InPostConnection.GetBaseUrl(context) + request.RequestUri!.PathAndQuery);
        request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", InPostConnection.GetApiToken(context));

        var response = await base.SendAsync(request, cancellationToken);
        try
        {
            var responseBody = await response.Content.ReadAsStringAsync(cancellationToken);

            var root = default(JsonElement);
            if (!string.IsNullOrWhiteSpace(responseBody))
            {
                try
                {
                    using var document = JsonDocument.Parse(responseBody);
                    root = document.RootElement.Clone();
                }
                catch (JsonException) when (!response.IsSuccessStatusCode)
                {
                    // Non-JSON error body (for example a gateway page); reported below with the raw body.
                }
            }

            if (!response.IsSuccessStatusCode || IsErrorEnvelope(root))
            {
                throw CreateException(response.StatusCode, root, responseBody);
            }

            return response;
        }
        catch
        {
            response.Dispose();
            throw;
        }
    }

    // ShipX sometimes returns an in-band error envelope with a 2xx status:
    // {"status": ..., "key": ..., "error": ...} (grounded in ShipXClient's response handling).
    private static bool IsErrorEnvelope(JsonElement root) =>
        root.ValueKind == JsonValueKind.Object &&
        root.TryGetProperty("error", out _) &&
        root.TryGetProperty("status", out _) &&
        root.TryGetProperty("key", out _);

    private static InPostApiException CreateException(HttpStatusCode statusCode, JsonElement root, string responseBody)
    {
        string? errorCode = null;
        if (root.ValueKind == JsonValueKind.Object && root.TryGetProperty("error", out var errorElement))
        {
            errorCode = errorElement.ValueKind == JsonValueKind.String ? errorElement.GetString() : errorElement.ToString();
        }

        var message = errorCode is null
            ? $"ShipX API request failed with status {(int)statusCode}. Body: {responseBody}"
            : $"ShipX API error '{errorCode}' (HTTP {(int)statusCode}).";
        return new InPostApiException(statusCode, errorCode, message);
    }
}
