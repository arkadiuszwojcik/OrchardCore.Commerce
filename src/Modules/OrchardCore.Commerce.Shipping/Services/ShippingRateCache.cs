#nullable enable
using Microsoft.Extensions.Caching.Memory;
using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Collections.Generic;
using System.Collections.Concurrent;
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
    private readonly ConcurrentDictionary<string, SemaphoreSlim> _gates = new();

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
        if (!policy.EnableCaching || rates.Count == 0)
        {
            return Task.CompletedTask;
        }

        var duration = policy.CacheDuration > TimeSpan.Zero
            ? policy.CacheDuration
            : TimeSpan.FromMinutes(15);

        _cache.Set(KeyPrefix + cacheKey, rates, duration);

        return Task.CompletedTask;
    }

    public async Task<IReadOnlyList<ProviderShippingRate>> GetOrCreateAsync(
        string cacheKey,
        ProviderRateCachePolicy policy,
        Func<CancellationToken, Task<IReadOnlyList<ProviderShippingRate>>> factory,
        CancellationToken cancellationToken = default)
    {
        if (await TryGetAsync(cacheKey, cancellationToken) is { } hit) return hit;

        var gate = _gates.GetOrAdd(cacheKey, _ => new SemaphoreSlim(1, 1));
        await gate.WaitAsync(cancellationToken);
        try
        {
            if (await TryGetAsync(cacheKey, cancellationToken) is { } second) return second;

            // An exception propagates to the caller and nothing is cached.
            var rates = await factory(cancellationToken);
            await SetAsync(cacheKey, rates, policy, cancellationToken);
            return rates;
        }
        finally
        {
            gate.Release();

            // Keep the gate dictionary bounded; a rare overlap only costs a duplicate provider call.
            if (gate.CurrentCount == 1)
            {
                _gates.TryRemove(new KeyValuePair<string, SemaphoreSlim>(cacheKey, gate));
            }
        }
    }
}
