using OrchardCore.Commerce.Shipping.Abstractions;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Rule-table implementation of <see cref="IShipmentStateTransitionValidator"/>.
/// Defines which <see cref="ShipmentFulfillmentStatus"/> transitions are permitted.
/// </summary>
public class ShipmentStateTransitionValidator : IShipmentStateTransitionValidator
{
    // Key = current status; Value = set of valid target statuses.
    private static readonly IReadOnlyDictionary<ShipmentFulfillmentStatus, IReadOnlySet<ShipmentFulfillmentStatus>>
        AllowedTransitions = new Dictionary<ShipmentFulfillmentStatus, IReadOnlySet<ShipmentFulfillmentStatus>>
        {
            [ShipmentFulfillmentStatus.Pending] = new HashSet<ShipmentFulfillmentStatus>
            {
                ShipmentFulfillmentStatus.AwaitingPickup,
                ShipmentFulfillmentStatus.Cancelled,
            },
            [ShipmentFulfillmentStatus.AwaitingPickup] = new HashSet<ShipmentFulfillmentStatus>
            {
                ShipmentFulfillmentStatus.InTransit,
                ShipmentFulfillmentStatus.Cancelled,
            },
            [ShipmentFulfillmentStatus.InTransit] = new HashSet<ShipmentFulfillmentStatus>
            {
                ShipmentFulfillmentStatus.Delivered,
                ShipmentFulfillmentStatus.Returned,
                ShipmentFulfillmentStatus.Lost,
            },
            [ShipmentFulfillmentStatus.Delivered] = new HashSet<ShipmentFulfillmentStatus>
            {
                ShipmentFulfillmentStatus.Returned,
            },
            [ShipmentFulfillmentStatus.Returned] = new HashSet<ShipmentFulfillmentStatus>(),
            [ShipmentFulfillmentStatus.Cancelled] = new HashSet<ShipmentFulfillmentStatus>(),
            [ShipmentFulfillmentStatus.Lost] = new HashSet<ShipmentFulfillmentStatus>(),
        };

    public Task<bool> CanTransitionAsync(
        ShipmentFulfillmentStatus currentStatus,
        ShipmentFulfillmentStatus targetStatus,
        CancellationToken cancellationToken = default)
    {
        if (currentStatus == targetStatus) return Task.FromResult(false);

        var allowed = AllowedTransitions.TryGetValue(currentStatus, out var targets)
            && targets.Contains(targetStatus);

        return Task.FromResult(allowed);
    }
}
