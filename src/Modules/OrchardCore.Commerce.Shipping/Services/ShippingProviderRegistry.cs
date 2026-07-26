using OrchardCore.Commerce.Shipping.Abstractions;
using System.Collections.Generic;
using System.Linq;

namespace OrchardCore.Commerce.Shipping.Services;

public class ShippingProviderRegistry : IShippingProviderRegistry
{
    private readonly IReadOnlyList<IShippingProvider> _providers;

    public ShippingProviderRegistry(IEnumerable<IShippingProvider> providers) =>
        _providers = providers.ToList();

    public IShippingProvider? GetProvider(string providerId) =>
        _providers.FirstOrDefault(p => p.ProviderId == providerId);

    public IReadOnlyList<IShippingProvider> GetAllProviders() => _providers;
}
