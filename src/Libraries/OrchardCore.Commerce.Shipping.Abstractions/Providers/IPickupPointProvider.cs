using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A pickup point (PUDO) location.
/// </summary>
public sealed record PickupPoint(
    string PickupPointId,
    string LocationName,
    AddressSnapshot Address,
    string? OpeningHours = null,
    string? DistanceFromDestination = null,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Request for searching pickup points.
/// </summary>
public sealed record PickupPointSearchRequest(
    AddressSnapshot DestinationAddress,
    int MaxResults = 10,
    string? ServiceId = null,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Provider capability for searching pickup/drop-off points (PUDO).
/// </summary>
public interface IPickupPointProvider : IShippingProvider
{
    /// <summary>
    /// Searches for pickup points near the destination address.
    /// </summary>
    Task<IReadOnlyList<PickupPoint>> SearchPickupPointsAsync(
        ShippingProviderConnectionContext context,
        PickupPointSearchRequest request,
        CancellationToken cancellationToken = default);
}
