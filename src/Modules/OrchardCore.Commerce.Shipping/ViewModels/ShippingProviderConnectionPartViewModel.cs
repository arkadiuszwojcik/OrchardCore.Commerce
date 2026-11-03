using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.ViewModels;

public class ShippingProviderConnectionPartViewModel
{
    public string ProviderKey { get; set; } = string.Empty;
    public string ConnectionName { get; set; } = string.Empty;
    public string CredentialsJson { get; set; } = string.Empty;
    public bool HasStoredCredentials { get; set; }
    public bool IsEnabled { get; set; }
    public bool IsTestMode { get; set; }
    public string LastHealthStatus { get; set; } = string.Empty;
    public string NormalizationProfileJson { get; set; } = string.Empty;
    public IEnumerable<string> AvailableProviderKeys { get; set; } = [];
}
