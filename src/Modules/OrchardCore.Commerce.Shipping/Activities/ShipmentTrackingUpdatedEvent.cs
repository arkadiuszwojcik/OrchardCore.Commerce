using Microsoft.Extensions.Localization;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Triggers when tracking information is updated for a shipment (webhook or poll).
/// </summary>
public class ShipmentTrackingUpdatedEvent : ShippingEventActivityBase
{
    public ShipmentTrackingUpdatedEvent(IStringLocalizer<ShipmentTrackingUpdatedEvent> localizer)
        : base(localizer) { }

    public override LocalizedString DisplayText => T["Shipment Tracking Updated"];
}
