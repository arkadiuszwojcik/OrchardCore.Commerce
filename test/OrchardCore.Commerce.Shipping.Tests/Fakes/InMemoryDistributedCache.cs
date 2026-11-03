using Microsoft.Extensions.Caching.Distributed;
using Microsoft.Extensions.Caching.Memory;
using Microsoft.Extensions.Options;

namespace OrchardCore.Commerce.Shipping.Tests.Fakes;

/// <summary>
/// An in-memory <see cref="IDistributedCache"/> for tests.
/// </summary>
public class InMemoryDistributedCache : MemoryDistributedCache
{
    public InMemoryDistributedCache()
        : base(Options.Create(new MemoryDistributedCacheOptions()))
    {
    }
}
