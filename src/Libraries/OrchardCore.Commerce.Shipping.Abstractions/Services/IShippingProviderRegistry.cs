using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Registry for all available shipping providers.
/// </summary>
public interface IShippingProviderRegistry
{
    /// <summary>
    /// Gets a provider by ID.
    /// </summary>
    IShippingProvider? GetProvider(string providerId);

    /// <summary>
    /// Gets all registered providers.
    /// </summary>
    IReadOnlyList<IShippingProvider> GetAllProviders();
}
