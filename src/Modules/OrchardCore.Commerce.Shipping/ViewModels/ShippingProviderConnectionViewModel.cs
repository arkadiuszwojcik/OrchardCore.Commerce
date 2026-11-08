using Microsoft.AspNetCore.Mvc.ModelBinding;

namespace OrchardCore.Commerce.Shipping.ViewModels;

/// <summary>
/// The fields every shipping provider connection has, regardless of the provider.
/// </summary>
public class ShippingProviderConnectionViewModel
{
    public string Name { get; set; } = string.Empty;
    public bool IsEnabled { get; set; } = true;
    public bool IsTestMode { get; set; }

    [BindNever]
    public string ProviderDisplayName { get; set; } = string.Empty;
}
