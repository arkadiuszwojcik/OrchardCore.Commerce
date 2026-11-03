using Microsoft.Extensions.Caching.Distributed;
using OrchardCore.Commerce.Shipping.Tests.Fakes;
using System;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class TestInfrastructureTests
{
    [Fact]
    public void FakeTimeProviderAdvanceMovesTime()
    {
        var time = new FakeTimeProvider();
        var start = time.GetUtcNow();

        time.Advance(TimeSpan.FromMinutes(3));

        Assert.Equal(start.AddMinutes(3), time.GetUtcNow());
    }

    [Fact]
    public async Task InMemoryDistributedCacheSetThenGetReturnsValue()
    {
        var cache = new InMemoryDistributedCache();
        var token = TestContext.Current.CancellationToken;

        await cache.SetAsync("key", [1, 2, 3], new DistributedCacheEntryOptions(), token);

        Assert.Equal(new byte[] { 1, 2, 3 }, await cache.GetAsync("key", token));
    }
}
