using OrchardCore.Commerce.MoneyDataType;
using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Response from purchasing a shipment.
/// </summary>
public sealed record ShipmentPurchaseResponse(
    string ShipmentId,
    string? TrackingNumber = null,
    string? TrackingUrl = null,
    string? LabelUrl = null,
    byte[]? LabelData = null,
    string? LabelFormat = null,
    Amount? ActualCost = null,
    IReadOnlyList<ShippingExtensionData>? ExtensionData = null,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Request for purchasing/creating a shipment.
/// </summary>
public sealed record ShipmentPurchaseRequest(
    string OrderId,
    string QuoteId,
    AddressSnapshot OriginAddress,
    AddressSnapshot DestinationAddress,
    IReadOnlyList<ShippingPackage> Packages,
    string ServiceId,
    PickupPointSnapshot? PickupPoint = null,
    DateTimeOffset? ShipDate = null,
    IReadOnlyList<ShippingExtensionConfiguration>? Extensions = null,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Provider capability for creating and managing shipments.
/// </summary>
public interface IShipmentProvider : IShippingProvider
{
    /// <summary>
    /// Purchases a shipment and retrieves label/tracking information.
    /// </summary>
    Task<ShipmentPurchaseResponse> PurchaseShipmentAsync(
        ShippingProviderConnectionContext context,
        ShipmentPurchaseRequest request,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Cancels/voids a previously purchased shipment.
    /// </summary>
    Task<bool> CancelShipmentAsync(
        ShippingProviderConnectionContext context,
        string shipmentId,
        CancellationToken cancellationToken = default);
}
