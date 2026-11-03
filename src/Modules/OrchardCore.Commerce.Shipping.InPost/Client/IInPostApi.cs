using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client.Models;
using Refit;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

/// <summary>
/// Refit definition of the InPost ShipX REST API. The connection context is passed as a request option and consumed by
/// <see cref="InPostConnectionHandler"/> (base URL, bearer token, error mapping).
/// </summary>
public interface IInPostApi
{
    [Get("/v1/organizations/{organizationId}")]
    Task<InPostOrganization?> GetOrganizationAsync(
        string organizationId,
        [Property(InPostConnectionHandler.ContextOptionKey)] ShippingProviderConnectionContext context,
        CancellationToken cancellationToken);

    [Post("/v1/organizations/{organizationId}/shipments/calculate")]
    Task<JsonElement> CalculatePricesAsync(
        string organizationId,
        [Body] InPostCalculateRequest body,
        [Property(InPostConnectionHandler.ContextOptionKey)] ShippingProviderConnectionContext context,
        CancellationToken cancellationToken);

    [Post("/v1/organizations/{organizationId}/shipments")]
    Task<InPostShipmentResponse?> CreateShipmentAsync(
        string organizationId,
        [Body] InPostShipmentRequest shipment,
        [Property(InPostConnectionHandler.ContextOptionKey)] ShippingProviderConnectionContext context,
        CancellationToken cancellationToken);

    [Delete("/v1/organizations/{organizationId}/shipments/{shipmentId}")]
    Task CancelShipmentAsync(
        string organizationId,
        string shipmentId,
        [Property(InPostConnectionHandler.ContextOptionKey)] ShippingProviderConnectionContext context,
        CancellationToken cancellationToken);

    [Get("/v1/organizations/{organizationId}/shipments")]
    Task<JsonElement> FindShipmentsByTrackingNumberAsync(
        string organizationId,
        [AliasAs("tracking_number")] string trackingNumber,
        [Property(InPostConnectionHandler.ContextOptionKey)] ShippingProviderConnectionContext context,
        CancellationToken cancellationToken);

    [Get("/v1/points")]
    Task<JsonElement> SearchPointsAsync(
        [Query] IDictionary<string, string> query,
        [Property(InPostConnectionHandler.ContextOptionKey)] ShippingProviderConnectionContext context,
        CancellationToken cancellationToken);
}
