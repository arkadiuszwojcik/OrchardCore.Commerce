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
    IShippingProvider? GetProvider(string providerKey);

    /// <summary>
    /// Gets the capabilities computed from the capability interfaces the provider implements.
    /// </summary>
    ShippingProviderCapabilities GetCapabilities(string providerKey);

    /// <summary>
    /// Gets all registered providers.
    /// </summary>
    IReadOnlyList<IShippingProvider> GetAllProviders();
}
