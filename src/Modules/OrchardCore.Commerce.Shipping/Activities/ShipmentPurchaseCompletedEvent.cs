using Microsoft.Extensions.Localization;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Triggers when a shipping label purchase is completed successfully.
/// </summary>
public class ShipmentPurchaseCompletedEvent : ShippingEventActivityBase
{
    public ShipmentPurchaseCompletedEvent(IStringLocalizer<ShipmentPurchaseCompletedEvent> localizer)
        : base(localizer) { }

    public override LocalizedString DisplayText => T["Shipment Purchase Completed"];
}
