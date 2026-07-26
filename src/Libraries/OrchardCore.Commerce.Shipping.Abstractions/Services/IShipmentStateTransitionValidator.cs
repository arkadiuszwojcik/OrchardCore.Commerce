using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Validates state transitions for shipments (e.g., cannot cancel a delivered shipment).
/// </summary>
public interface IShipmentStateTransitionValidator
{
    /// <summary>
    /// Checks if a state transition is valid.
    /// </summary>
    Task<bool> CanTransitionAsync(
        ShipmentFulfillmentStatus currentStatus,
        ShipmentFulfillmentStatus targetStatus,
        CancellationToken cancellationToken = default);
}
