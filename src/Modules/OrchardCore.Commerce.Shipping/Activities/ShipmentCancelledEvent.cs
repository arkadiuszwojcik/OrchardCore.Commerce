using Microsoft.Extensions.Localization;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Triggers when a shipment is cancelled.
/// </summary>
public class ShipmentCancelledEvent : ShippingEventActivityBase
{
    public ShipmentCancelledEvent(IStringLocalizer<ShipmentCancelledEvent> localizer)
        : base(localizer) { }

    public override LocalizedString DisplayText => T["Shipment Cancelled"];
}
