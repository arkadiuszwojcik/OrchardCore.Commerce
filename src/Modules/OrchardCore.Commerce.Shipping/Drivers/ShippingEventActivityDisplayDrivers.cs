using Lombiq.HelpfulLibraries.OrchardCore.Workflow;
using Microsoft.AspNetCore.Mvc.Localization;
using OrchardCore.Commerce.Shipping.Activities;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShippingQuoteSelectedEventDisplayDriver
    : SimpleEventActivityDisplayDriverBase<ShippingQuoteSelectedEvent>
{
    public override string IconClass => "fa-tag";
    public override LocalizedHtmlString Description =>
        H["Triggers when a customer selects a shipping quote at checkout."];

    public ShippingQuoteSelectedEventDisplayDriver(
        IHtmlLocalizer<ShippingQuoteSelectedEventDisplayDriver> htmlLocalizer) =>
        H = htmlLocalizer;

    private IHtmlLocalizer H { get; }
}

public class ShipmentCreatedEventDisplayDriver
    : SimpleEventActivityDisplayDriverBase<ShipmentCreatedEvent>
{
    public override string IconClass => "fa-box";
    public override LocalizedHtmlString Description =>
        H["Triggers when a shipment content item is created."];

    public ShipmentCreatedEventDisplayDriver(
        IHtmlLocalizer<ShipmentCreatedEventDisplayDriver> htmlLocalizer) =>
        H = htmlLocalizer;

    private IHtmlLocalizer H { get; }
}

public class ShipmentPurchaseCompletedEventDisplayDriver
    : SimpleEventActivityDisplayDriverBase<ShipmentPurchaseCompletedEvent>
{
    public override string IconClass => "fa-check-circle";
    public override LocalizedHtmlString Description =>
        H["Triggers when a shipping label purchase completes successfully."];

    public ShipmentPurchaseCompletedEventDisplayDriver(
        IHtmlLocalizer<ShipmentPurchaseCompletedEventDisplayDriver> htmlLocalizer) =>
        H = htmlLocalizer;

    private IHtmlLocalizer H { get; }
}

public class ShipmentPurchaseFailedEventDisplayDriver
    : SimpleEventActivityDisplayDriverBase<ShipmentPurchaseFailedEvent>
{
    public override string IconClass => "fa-times-circle";
    public override LocalizedHtmlString Description =>
        H["Triggers when a shipping label purchase fails."];

    public ShipmentPurchaseFailedEventDisplayDriver(
        IHtmlLocalizer<ShipmentPurchaseFailedEventDisplayDriver> htmlLocalizer) =>
        H = htmlLocalizer;

    private IHtmlLocalizer H { get; }
}

public class ShipmentCancelledEventDisplayDriver
    : SimpleEventActivityDisplayDriverBase<ShipmentCancelledEvent>
{
    public override string IconClass => "fa-ban";
    public override LocalizedHtmlString Description =>
        H["Triggers when a shipment is cancelled."];

    public ShipmentCancelledEventDisplayDriver(
        IHtmlLocalizer<ShipmentCancelledEventDisplayDriver> htmlLocalizer) =>
        H = htmlLocalizer;

    private IHtmlLocalizer H { get; }
}

public class ShipmentTrackingUpdatedEventDisplayDriver
    : SimpleEventActivityDisplayDriverBase<ShipmentTrackingUpdatedEvent>
{
    public override string IconClass => "fa-map-marker-alt";
    public override LocalizedHtmlString Description =>
        H["Triggers when tracking information is updated for a shipment."];

    public ShipmentTrackingUpdatedEventDisplayDriver(
        IHtmlLocalizer<ShipmentTrackingUpdatedEventDisplayDriver> htmlLocalizer) =>
        H = htmlLocalizer;

    private IHtmlLocalizer H { get; }
}
