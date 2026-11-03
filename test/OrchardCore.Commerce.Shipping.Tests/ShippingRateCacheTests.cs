using Microsoft.Extensions.Caching.Memory;
using OrchardCore.Commerce.MoneyDataType;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Services;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class ShippingRateCacheTests
{
    private static readonly ProviderRateCachePolicy _policy = new(EnableCaching: true, CacheDuration: TimeSpan.FromMinutes(5));

    private static readonly IReadOnlyList<ProviderShippingRate> _rates =
    [
        new("std", "Standard", new Amount(10, Currency.UnspecifiedCurrency), []),
    ];

    private static ShippingRateCache Create() => new(new MemoryCache(new MemoryCacheOptions()));

    [Fact]
    public async Task ConcurrentCallsForSameKeyInvokeFactoryOnce()
    {
        var cache = Create();
        var calls = 0;

        async Task<IReadOnlyList<ProviderShippingRate>> Factory(CancellationToken ct)
        {
            Interlocked.Increment(ref calls);
            await Task.Delay(100, ct);
            return _rates;
        }

        var results = await Task.WhenAll(
            Enumerable.Range(0, 10).Select(_ => cache.GetOrCreateAsync("k", _policy, Factory, TestContext.Current.CancellationToken)));

        Assert.Equal(1, calls);
        Assert.All(results, r => Assert.Single(r));
    }

    [Fact]
    public async Task FailuresAreNotCached()
    {
        var cache = Create();
        var calls = 0;

        Task<IReadOnlyList<ProviderShippingRate>> Factory(CancellationToken ct)
        {
            return ++calls == 1
                ? throw new InvalidOperationException("boom")
                : Task.FromResult(_rates);
        }

        await Assert.ThrowsAsync<InvalidOperationException>(() =>
            cache.GetOrCreateAsync("k", _policy, Factory, TestContext.Current.CancellationToken));
        var second = await cache.GetOrCreateAsync("k", _policy, Factory, TestContext.Current.CancellationToken);

        Assert.Single(second);
        Assert.Equal(2, calls);
    }

    [Fact]
    public async Task EmptyResultsAreNotCached()
    {
        var cache = Create();
        var calls = 0;

        Task<IReadOnlyList<ProviderShippingRate>> Factory(CancellationToken ct)
        {
            calls++;
            return Task.FromResult<IReadOnlyList<ProviderShippingRate>>([]);
        }

        await cache.GetOrCreateAsync("k", _policy, Factory, TestContext.Current.CancellationToken);
        await cache.GetOrCreateAsync("k", _policy, Factory, TestContext.Current.CancellationToken);

        Assert.Equal(2, calls);
    }

    [Fact]
    public async Task SuccessfulResultIsServedFromCache()
    {
        var cache = Create();
        var calls = 0;

        Task<IReadOnlyList<ProviderShippingRate>> Factory(CancellationToken ct)
        {
            calls++;
            return Task.FromResult(_rates);
        }

        await cache.GetOrCreateAsync("k", _policy, Factory, TestContext.Current.CancellationToken);
        await cache.GetOrCreateAsync("k", _policy, Factory, TestContext.Current.CancellationToken);

        Assert.Equal(1, calls);
    }
}
