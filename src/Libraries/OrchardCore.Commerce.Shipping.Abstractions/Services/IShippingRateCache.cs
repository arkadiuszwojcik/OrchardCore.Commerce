using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Cache for provider rate responses.
/// </summary>
public interface IShippingRateCache
{
    /// <summary>
    /// Tries to get cached rates.
    /// </summary>
    Task<IReadOnlyList<ProviderShippingRate>?> TryGetAsync(
        string cacheKey,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Stores rates in cache.
    /// </summary>
    Task SetAsync(
        string cacheKey,
        IReadOnlyList<ProviderShippingRate> rates,
        ProviderRateCachePolicy policy,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Returns cached rates or runs <paramref name="factory"/> once per key at a time (single-flight). Failures and
    /// empty results are never cached.
    /// </summary>
    Task<IReadOnlyList<ProviderShippingRate>> GetOrCreateAsync(
        string cacheKey,
        ProviderRateCachePolicy policy,
        System.Func<CancellationToken, Task<IReadOnlyList<ProviderShippingRate>>> factory,
        CancellationToken cancellationToken = default);
}
