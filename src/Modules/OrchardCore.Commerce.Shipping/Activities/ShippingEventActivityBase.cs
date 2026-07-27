using Lombiq.HelpfulLibraries.OrchardCore.Workflow;
using Microsoft.Extensions.Localization;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Base class for all shipping workflow events.
/// </summary>
public abstract class ShippingEventActivityBase : SimpleEventActivityBase
{
    public override LocalizedString Category => T["Shipping"];

    protected ShippingEventActivityBase(IStringLocalizer stringLocalizer)
        : base(stringLocalizer)
    {
    }
}
