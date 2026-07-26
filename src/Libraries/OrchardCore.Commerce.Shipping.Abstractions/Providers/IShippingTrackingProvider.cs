using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A single tracking event.
/// </summary>
public sealed record ShippingTrackingEvent(
    DateTimeOffset Timestamp,
    ShipmentTrackingStatus Status,
    string? Location = null,
    string? Description = null,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Tracking response from a provider.
/// </summary>
public sealed record ShippingTrackingResponse(
    string TrackingNumber,
    ShipmentTrackingStatus CurrentStatus,
    IReadOnlyList<ShippingTrackingEvent> Events,
    DateTimeOffset? EstimatedDelivery = null,
    DateTimeOffset? ActualDelivery = null,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Provider capability for tracking shipments.
/// </summary>
public interface IShippingTrackingProvider : IShippingProvider
{
    /// <summary>
    /// Retrieves tracking information for a shipment.
    /// </summary>
    Task<ShippingTrackingResponse> GetTrackingAsync(
        ShippingProviderConnectionContext context,
        string trackingNumber,
        CancellationToken cancellationToken = default);
}
