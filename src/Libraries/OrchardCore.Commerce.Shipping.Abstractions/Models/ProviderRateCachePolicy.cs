using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Cache policy for provider rate responses.
/// </summary>
public sealed record ProviderRateCachePolicy(
    bool EnableCaching = false,
    TimeSpan CacheDuration = default,
    bool CacheByAddress = true,
    bool CacheByWeight = true);
