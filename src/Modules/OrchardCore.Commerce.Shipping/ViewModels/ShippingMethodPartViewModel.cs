namespace OrchardCore.Commerce.Shipping.ViewModels;

public class ShippingMethodPartViewModel
{
    public string DisplayName { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public string ProviderConnectionId { get; set; } = string.Empty;
    public string ServiceId { get; set; } = string.Empty;
    public string OriginId { get; set; } = string.Empty;
    public bool IsEnabled { get; set; }
    public int SortOrder { get; set; }
    public string IconUrl { get; set; } = string.Empty;
}
