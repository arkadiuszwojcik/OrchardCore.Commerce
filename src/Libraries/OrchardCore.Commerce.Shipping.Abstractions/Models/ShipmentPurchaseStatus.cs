namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Status of a shipment purchase (label/tracking creation).
/// </summary>
public enum ShipmentPurchaseStatus
{
    NotPurchased,
    Pending,
    Purchased,
    Failed,
    Cancelled,
}
