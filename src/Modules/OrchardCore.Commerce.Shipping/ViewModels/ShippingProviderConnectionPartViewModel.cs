using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.ViewModels;

public class ShippingProviderConnectionPartViewModel
{
    public string ProviderId { get; set; } = string.Empty;
    public string ConnectionName { get; set; } = string.Empty;
    public string CredentialsJson { get; set; } = string.Empty;
    public bool IsEnabled { get; set; }
    public bool IsTestMode { get; set; }
    public string LastHealthStatus { get; set; } = string.Empty;
    public string NormalizationProfileJson { get; set; } = string.Empty;
    public IEnumerable<string> AvailableProviderIds { get; set; } = [];
}
