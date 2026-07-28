using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client.Models;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Net.Http.Json;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

/// <summary>
/// Thin HTTP client for the InPost ShipX REST API. Base URLs, authentication, endpoint paths, request/response
/// shapes and the error-envelope convention are grounded in the two reference PHP plugins
/// (inpost-for-woocommerce, inpostshipping-presta), not guessed:
/// <list type="bullet">
/// <item>Base URLs: production <c>https://api-shipx-pl.easypack24.net</c>, sandbox
/// <c>https://sandbox-api-shipx-pl.easypack24.net</c> (confirmed independently in both plugins).</item>
/// <item>Auth: <c>Authorization: Bearer {ApiToken}</c>.</item>
/// <item>Errors: a non-2xx response, or a 2xx body shaped like <c>{"status": ..., "key": ..., "error": ...}</c>,
/// both signal failure (see <c>ShipXClient::getExceptionByErrorCode</c>, PrestaShop plugin).</item>
/// <item>List endpoints (points, shipments) return <c>{"items": [...], ...}</c> or a bare array
/// (<c>GetAllTrait</c>/<c>ShipXCollection</c>, PrestaShop plugin).</item>
/// </list>
/// The exact JSON shape of shipment "offers" (as returned by <c>/shipments/calculate</c>) was not directly
/// observable in the PHP source (that code only forwards the raw response), so it is exposed here as a raw
/// <see cref="JsonElement"/> for the caller to parse defensively.
/// </summary>
public class InPostApiClient
{
    private const string ProductionBaseUrl = "https://api-shipx-pl.easypack24.net";
    private const string SandboxBaseUrl = "https://sandbox-api-shipx-pl.easypack24.net";

    private static readonly JsonSerializerOptions _jsonOptions = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull,
    };

    private readonly HttpClient _httpClient;

    public InPostApiClient(HttpClient httpClient) => _httpClient = httpClient;

    /// <summary>
    /// <c>GET /v1/organizations/{organizationId}</c> - used to validate that the configured API token and
    /// organization ID are valid (see <see cref="OrchardCore.Commerce.Shipping.InPost.InPostShippingProvider.TestConnectionAsync"/>).
    /// </summary>
    public async Task<InPostOrganization?> GetOrganizationAsync(
        ShippingProviderConnectionContext context, CancellationToken cancellationToken)
    {
        var organizationId = GetOrganizationId(context);
        var json = await SendAsync(context, HttpMethod.Get, $"/v1/organizations/{organizationId}", body: null, cancellationToken);
        return Deserialize<InPostOrganization>(json);
    }

    /// <summary>
    /// <c>POST /v1/organizations/{organizationId}/shipments/calculate</c> with body <c>{"shipments": [...]}</c>.
    /// Returns each priced shipment result (still containing its "offers") as a raw <see cref="JsonElement"/>,
    /// in the same order as <paramref name="shipments"/>.
    /// </summary>
    public async Task<IReadOnlyList<JsonElement>> CalculatePricesAsync(
        ShippingProviderConnectionContext context,
        IReadOnlyList<InPostShipmentRequest> shipments,
        CancellationToken cancellationToken)
    {
        var organizationId = GetOrganizationId(context);
        var body = new { shipments };
        var json = await SendAsync(
            context, HttpMethod.Post, $"/v1/organizations/{organizationId}/shipments/calculate", body, cancellationToken);
        return ExtractArray(json, "shipments");
    }

    /// <summary>
    /// <c>POST /v1/organizations/{organizationId}/shipments</c> - creates (purchases) a shipment.
    /// </summary>
    public async Task<InPostShipmentResponse?> CreateShipmentAsync(
        ShippingProviderConnectionContext context,
        InPostShipmentRequest shipment,
        CancellationToken cancellationToken)
    {
        var organizationId = GetOrganizationId(context);
        var json = await SendAsync(
            context, HttpMethod.Post, $"/v1/organizations/{organizationId}/shipments", shipment, cancellationToken);
        return Deserialize<InPostShipmentResponse>(json);
    }

    /// <summary>
    /// <c>DELETE /v1/organizations/{organizationId}/shipments/{shipmentId}</c>. ShipX only allows this while the
    /// shipment has not yet been confirmed/dispatched; a rejection surfaces as an <see cref="InPostApiException"/>.
    /// </summary>
    public Task CancelShipmentAsync(ShippingProviderConnectionContext context, string shipmentId, CancellationToken cancellationToken)
    {
        var organizationId = GetOrganizationId(context);
        return SendAsync(context, HttpMethod.Delete, $"/v1/organizations/{organizationId}/shipments/{shipmentId}", body: null, cancellationToken);
    }

    /// <summary>
    /// There is no dedicated "track by tracking number" endpoint in either reference plugin; both poll the
    /// shipments collection filtered by an attribute (they filter by <c>id</c>). This applies the same,
    /// grounded collection-filter mechanism to the <c>tracking_number</c> attribute:
    /// <c>GET /v1/organizations/{organizationId}/shipments?tracking_number={trackingNumber}</c>.
    /// </summary>
    public async Task<InPostShipmentResponse?> FindShipmentByTrackingNumberAsync(
        ShippingProviderConnectionContext context, string trackingNumber, CancellationToken cancellationToken)
    {
        var organizationId = GetOrganizationId(context);
        var path = $"/v1/organizations/{organizationId}/shipments?tracking_number={Uri.EscapeDataString(trackingNumber)}";
        var json = await SendAsync(context, HttpMethod.Get, path, body: null, cancellationToken);
        var items = ExtractArray(json, "items");
        return items.Count > 0 ? Deserialize<InPostShipmentResponse>(items[0]) : null;
    }

    /// <summary>
    /// <c>GET /v1/points</c> - pickup/drop-off point search. Query parameter names beyond the base path were not
    /// directly observable in the PHP source (only <c>BASE_PATH = '/v1/points'</c> was), so callers supply the
    /// exact query string to use.
    /// </summary>
    public async Task<IReadOnlyList<JsonElement>> SearchPointsAsync(
        ShippingProviderConnectionContext context, IDictionary<string, string> query, CancellationToken cancellationToken)
    {
        var queryString = string.Join(
            "&", query.Select(pair => $"{Uri.EscapeDataString(pair.Key)}={Uri.EscapeDataString(pair.Value)}"));
        var path = queryString.Length > 0 ? $"/v1/points?{queryString}" : "/v1/points";
        var json = await SendAsync(context, HttpMethod.Get, path, body: null, cancellationToken);
        return ExtractArray(json, "items");
    }

    private static T? Deserialize<T>(JsonElement json)
        where T : class =>
        json.ValueKind is JsonValueKind.Undefined or JsonValueKind.Null ? null : json.Deserialize<T>(_jsonOptions);

    // Collection endpoints wrap results as {"<propertyName>": [...]} (e.g. "items" for GetAllTrait-based
    // resources, "shipments" for /calculate which mirrors its own request envelope), falling back to a bare
    // array if the wrapper is absent.
    private static IReadOnlyList<JsonElement> ExtractArray(JsonElement json, string propertyName)
    {
        if (json.ValueKind == JsonValueKind.Object &&
            json.TryGetProperty(propertyName, out var wrapped) &&
            wrapped.ValueKind == JsonValueKind.Array)
        {
            return wrapped.EnumerateArray().ToList();
        }

        return json.ValueKind == JsonValueKind.Array ? json.EnumerateArray().ToList() : Array.Empty<JsonElement>();
    }

    private async Task<JsonElement> SendAsync(
        ShippingProviderConnectionContext context, HttpMethod method, string path, object? body, CancellationToken cancellationToken)
    {
        using var requestMessage = new HttpRequestMessage(method, GetBaseUrl(context) + path)
        {
            Headers = { Authorization = new AuthenticationHeaderValue("Bearer", GetApiToken(context)) },
        };

        if (body is not null)
        {
            requestMessage.Content = JsonContent.Create(body, options: _jsonOptions);
        }

        using var response = await _httpClient.SendAsync(requestMessage, cancellationToken);
        var responseBody = await response.Content.ReadAsStringAsync(cancellationToken);

        var root = default(JsonElement);
        if (!string.IsNullOrWhiteSpace(responseBody))
        {
            using var document = JsonDocument.Parse(responseBody);
            root = document.RootElement.Clone();
        }

        if (!response.IsSuccessStatusCode || IsErrorEnvelope(root))
        {
            throw CreateException(response.StatusCode, root, responseBody);
        }

        return root;
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

    private static bool IsSandbox(ShippingProviderConnectionContext context) =>
        context.Metadata is not null &&
        context.Metadata.TryGetValue("IsTestMode", out var value) &&
        value switch
        {
            bool flag => flag,
            string text => bool.TryParse(text, out var parsed) && parsed,
            _ => false,
        };

    private static string GetBaseUrl(ShippingProviderConnectionContext context) =>
        IsSandbox(context) ? SandboxBaseUrl : ProductionBaseUrl;

    private static string GetOrganizationId(ShippingProviderConnectionContext context) =>
        GetRequiredCredential(context, "OrganizationId");

    private static string GetApiToken(ShippingProviderConnectionContext context) =>
        GetRequiredCredential(context, "ApiToken");

    private static string GetRequiredCredential(ShippingProviderConnectionContext context, string key) =>
        context.Credentials.TryGetValue(key, out var value) && !string.IsNullOrEmpty(value)
            ? value
            : throw new InvalidOperationException($"The InPost connection is missing the required '{key}' credential.");
}
