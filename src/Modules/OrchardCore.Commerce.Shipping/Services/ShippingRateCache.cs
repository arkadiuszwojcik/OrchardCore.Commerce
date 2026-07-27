#nullable enable
using Microsoft.Extensions.Caching.Memory;
using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// IMemoryCache-backed implementation of <see cref="IShippingRateCache"/>.
/// </summary>
public class ShippingRateCache : IShippingRateCache
{
    private const string KeyPrefix = "ShippingRate:";

    private readonly IMemoryCache _cache;

    public ShippingRateCache(IMemoryCache cache) => _cache = cache;

    public Task<IReadOnlyList<ProviderShippingRate>?> TryGetAsync(
        string cacheKey,
        CancellationToken cancellationToken = default)
    {
        _cache.TryGetValue(KeyPrefix + cacheKey, out IReadOnlyList<ProviderShippingRate>? rates);
        return Task.FromResult(rates);
    }

    public Task SetAsync(
        string cacheKey,
        IReadOnlyList<ProviderShippingRate> rates,
        ProviderRateCachePolicy policy,
        CancellationToken cancellationToken = default)
    {
        if (!policy.EnableCaching)
        {
            return Task.CompletedTask;
        }

        var duration = policy.CacheDuration > TimeSpan.Zero
            ? policy.CacheDuration
            : TimeSpan.FromMinutes(15);

        _cache.Set(KeyPrefix + cacheKey, rates, duration);

        return Task.CompletedTask;
    }
}
