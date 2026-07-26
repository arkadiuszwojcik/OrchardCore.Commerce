namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Carrier tracking status (from provider API).
/// </summary>
public enum ShipmentTrackingStatus
{
    Unknown,
    InfoReceived,
    InTransit,
    OutForDelivery,
    Delivered,
    AvailableForPickup,
    Exception,
    Expired,
    Pending,
}
