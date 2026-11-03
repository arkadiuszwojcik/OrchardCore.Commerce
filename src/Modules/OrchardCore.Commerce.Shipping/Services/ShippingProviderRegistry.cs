#nullable enable
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Resolves providers by case-insensitive ID. Duplicates and capability mismatches are logged
/// instead of failing the tenant.
/// </summary>
public class ShippingProviderRegistry : IShippingProviderRegistry
{
    private readonly IReadOnlyList<IShippingProvider> _providers;
    private readonly Dictionary<string, IShippingProvider> _byId = new(StringComparer.OrdinalIgnoreCase);

    public ShippingProviderRegistry(
        IEnumerable<IShippingProvider> providers,
        ILogger<ShippingProviderRegistry> logger)
    {
        var list = new List<IShippingProvider>();

        foreach (var provider in providers)
        {
            if (string.IsNullOrWhiteSpace(provider.ProviderKey))
            {
                logger.LogError("Shipping provider {Type} has an empty ProviderKey and was ignored.", provider.GetType().FullName);
                continue;
            }

            if (!_byId.TryAdd(provider.ProviderKey, provider))
            {
                logger.LogError(
                    "Duplicate shipping provider ID '{ProviderKey}' ({Type}) was ignored.",
                    provider.ProviderKey,
                    provider.GetType().FullName);
                continue;
            }

            list.Add(provider);
        }

        _providers = list;
    }

    public IShippingProvider? GetProvider(string providerKey) =>
        string.IsNullOrEmpty(providerKey) ? null : _byId.GetValueOrDefault(providerKey);

    public ShippingProviderCapabilities GetCapabilities(string providerKey) =>
        GetProvider(providerKey) is { } provider
            ? ShippingProviderCapabilitiesExtensions.Compute(provider)
            : ShippingProviderCapabilities.None;

    public IReadOnlyList<IShippingProvider> GetAllProviders() => _providers;
}
