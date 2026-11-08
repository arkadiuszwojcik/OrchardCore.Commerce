using OrchardCore.DisplayManagement;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.ViewModels;

public class ShippingProviderConnectionListItem
{
    public string Id { get; set; } = string.Empty;
    public string Name { get; set; } = string.Empty;
    public string ProviderKey { get; set; } = string.Empty;
    public string ProviderDisplayName { get; set; } = string.Empty;
    public bool IsEnabled { get; set; }
    public bool IsTestMode { get; set; }
}

public class ShippingProviderOption
{
    public string ProviderKey { get; set; } = string.Empty;
    public string DisplayName { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
}

public class ShippingProviderConnectionListViewModel
{
    public string Search { get; set; } = string.Empty;
    public IList<ShippingProviderConnectionListItem> Connections { get; set; } = [];
    public IList<ShippingProviderOption> Providers { get; set; } = [];
}

public class ShippingProviderConnectionEditorViewModel
{
    public string DisplayName { get; set; } = string.Empty;
    public bool IsNew { get; set; }
    public IShape Editor { get; set; } = default!;
}
