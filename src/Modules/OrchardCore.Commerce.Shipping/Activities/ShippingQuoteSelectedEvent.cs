using Microsoft.Extensions.Localization;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Triggers when a customer selects a shipping quote at checkout.
/// </summary>
public class ShippingQuoteSelectedEvent : ShippingEventActivityBase
{
    public ShippingQuoteSelectedEvent(IStringLocalizer<ShippingQuoteSelectedEvent> localizer)
        : base(localizer) { }

    public override LocalizedString DisplayText => T["Shipping Quote Selected"];
}
