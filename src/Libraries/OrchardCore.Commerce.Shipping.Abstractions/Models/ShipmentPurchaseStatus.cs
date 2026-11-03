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

    /// <summary>
    /// The carrier call was made but its outcome is unknown (timeout, connection loss). A shipment may exist at the
    /// carrier; it must not be retried automatically.
    /// </summary>
    Uncertain,
}
