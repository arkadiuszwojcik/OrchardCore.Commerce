namespace OrchardCore.Commerce.Shipping.InPost.Constants;

/// <summary>
/// InPost ShipX "sending method" values, describing how the parcel is handed over to InPost. These
/// correspond to the <c>custom_attributes.sending_method</c> field of a ShipX shipment.
/// </summary>
public static class InPostSendingMethods
{
    /// <summary>
    /// The sender hands the parcel to a courier who picks it up (dispatch order).
    /// </summary>
    public const string DispatchOrder = "dispatch_order";

    /// <summary>
    /// The sender drops the parcel off at a parcel locker (Paczkomat).
    /// </summary>
    public const string ParcelLocker = "parcel_locker";

    /// <summary>
    /// The sender drops the parcel off at a partner point of service (POP).
    /// </summary>
    public const string PointOfService = "pop";
}
