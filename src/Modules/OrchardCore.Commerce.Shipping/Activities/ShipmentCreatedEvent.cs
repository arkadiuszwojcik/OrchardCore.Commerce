using Microsoft.Extensions.Localization;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Triggers when a shipment content item is created.
/// </summary>
public class ShipmentCreatedEvent : ShippingEventActivityBase
{
    public ShipmentCreatedEvent(IStringLocalizer<ShipmentCreatedEvent> localizer)
        : base(localizer) { }

    public override LocalizedString DisplayText => T["Shipment Created"];
}
