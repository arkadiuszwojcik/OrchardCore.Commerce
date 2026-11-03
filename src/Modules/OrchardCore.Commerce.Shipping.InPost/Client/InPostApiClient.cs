using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client.Models;
using Refit;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Runtime.ExceptionServices;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

/// <summary>
/// Facade over the Refit-based <see cref="IInPostApi"/> for the InPost ShipX REST API. Base URLs, authentication,
/// endpoint paths, request/response shapes and the error-envelope convention are grounded in the two reference PHP
/// plugins (inpost-for-woocommerce, inpostshipping-presta), not guessed:
/// <list type="bullet">
/// <item>Base URLs: production <c>https://api-shipx-pl.easypack24.net</c>, sandbox
/// <c>https://sandbox-api-shipx-pl.easypack24.net</c> (confirmed independently in both plugins).</item>
/// <item>Auth: <c>Authorization: Bearer {ApiToken}</c>.</item>
/// <item>Errors: a non-2xx response, or a 2xx body shaped like <c>{"status": ..., "key": ..., "error": ...}</c>,
/// both signal failure (see <c>ShipXClient::getExceptionByErrorCode</c>, PrestaShop plugin).</item>
/// <item>List endpoints (points, shipments) return <c>{"items": [...], ...}</c> or a bare array
/// (<c>GetAllTrait</c>/<c>ShipXCollection</c>, PrestaShop plugin).</item>
/// </list>
/// Authentication, base URL selection and error mapping are handled by <see cref="InPostConnectionHandler"/>; retries
/// by the resilience handler registered in <see cref="ServiceCollectionExtensions.AddInPostApiClient"/>.
/// The exact JSON shape of shipment "offers" (as returned by <c>/shipments/calculate</c>) was not directly
/// observable in the PHP source (that code only forwards the raw response), so it is exposed here as a raw
/// <see cref="JsonElement"/> for the caller to parse defensively.
/// </summary>
public class InPostApiClient
{
    private readonly IInPostApi _api;

    public InPostApiClient(IInPostApi api) => _api = api;

    /// <summary>
    /// <c>GET /v1/organizations/{organizationId}</c> - used to validate that the configured API token and
    /// organization ID are valid (see <see cref="OrchardCore.Commerce.Shipping.InPost.InPostShippingProvider.TestConnectionAsync"/>).
    /// </summary>
    public Task<InPostOrganization?> GetOrganizationAsync(
        ShippingProviderConnectionContext context, CancellationToken cancellationToken) =>
        UnwrapAsync(() => _api.GetOrganizationAsync(InPostConnection.GetOrganizationId(context), context, cancellationToken));

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
        var json = await UnwrapAsync(() => _api.CalculatePricesAsync(
            InPostConnection.GetOrganizationId(context), new InPostCalculateRequest(shipments), context, cancellationToken));
        return ExtractArray(json, "shipments");
    }

    /// <summary>
    /// <c>POST /v1/organizations/{organizationId}/shipments</c> - creates (purchases) a shipment.
    /// </summary>
    public Task<InPostShipmentResponse?> CreateShipmentAsync(
        ShippingProviderConnectionContext context,
        InPostShipmentRequest shipment,
        CancellationToken cancellationToken) =>
        UnwrapAsync(() => _api.CreateShipmentAsync(InPostConnection.GetOrganizationId(context), shipment, context, cancellationToken));

    /// <summary>
    /// <c>DELETE /v1/organizations/{organizationId}/shipments/{shipmentId}</c>. ShipX only allows this while the
    /// shipment has not yet been confirmed/dispatched; a rejection surfaces as an <see cref="InPostApiException"/>.
    /// </summary>
    public Task CancelShipmentAsync(ShippingProviderConnectionContext context, string shipmentId, CancellationToken cancellationToken) =>
        UnwrapAsync(async () =>
        {
            await _api.CancelShipmentAsync(InPostConnection.GetOrganizationId(context), shipmentId, context, cancellationToken);
            return 0;
        });

    /// <summary>
    /// There is no dedicated "track by tracking number" endpoint in either reference plugin; both poll the
    /// shipments collection filtered by an attribute (they filter by <c>id</c>). This applies the same,
    /// grounded collection-filter mechanism to the <c>tracking_number</c> attribute:
    /// <c>GET /v1/organizations/{organizationId}/shipments?tracking_number={trackingNumber}</c>.
    /// </summary>
    public async Task<InPostShipmentResponse?> FindShipmentByTrackingNumberAsync(
        ShippingProviderConnectionContext context, string trackingNumber, CancellationToken cancellationToken)
    {
        var json = await UnwrapAsync(() => _api.FindShipmentsByTrackingNumberAsync(
            InPostConnection.GetOrganizationId(context), trackingNumber, context, cancellationToken));
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
        var json = await UnwrapAsync(() => _api.SearchPointsAsync(query, context, cancellationToken));
        return ExtractArray(json, "items");
    }

    // Refit wraps exceptions thrown by the HTTP pipeline in ApiRequestException; callers expect the original ones
    // (InPostApiException in particular).
    private static async Task<T> UnwrapAsync<T>(Func<Task<T>> call)
    {
        try
        {
            return await call();
        }
        catch (ApiRequestException exception) when (exception.InnerException is { } inner)
        {
            ExceptionDispatchInfo.Capture(inner).Throw();
            throw;
        }
    }

    private static T? Deserialize<T>(JsonElement json)
        where T : class =>
        json.ValueKind is JsonValueKind.Undefined or JsonValueKind.Null ? null : json.Deserialize<T>(InPostJson.Options);

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
}
