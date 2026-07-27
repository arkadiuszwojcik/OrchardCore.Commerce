using Microsoft.Extensions.Localization;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Triggers when a shipping label purchase fails.
/// </summary>
public class ShipmentPurchaseFailedEvent : ShippingEventActivityBase
{
    public ShipmentPurchaseFailedEvent(IStringLocalizer<ShipmentPurchaseFailedEvent> localizer)
        : base(localizer) { }

    public override LocalizedString DisplayText => T["Shipment Purchase Failed"];
}
