namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Fulfillment status of a shipment (merchant's side).
/// </summary>
public enum ShipmentFulfillmentStatus
{
    Pending,
    AwaitingPickup,
    InTransit,
    Delivered,
    Returned,
    Cancelled,
    Lost,
}
