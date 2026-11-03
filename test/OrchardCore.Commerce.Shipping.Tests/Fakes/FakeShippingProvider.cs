using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Tests.Fakes;

/// <summary>
/// A configurable rate provider that records how often it was called.
/// </summary>
public class FakeShippingProvider : IShippingRateProvider
{
    private int _rateCallCount;

    public string ProviderKey { get; init; } = "Fake";

    public IReadOnlyList<ProviderShippingRate> Rates { get; set; } = [];

    public Exception? ExceptionToThrow { get; set; }

    public ProviderRateCachePolicy CachePolicy { get; set; } = new(EnableCaching: true, CacheDuration: TimeSpan.FromMinutes(5));

    public int RateCallCount => _rateCallCount;

    public ShippingProviderDescriptor GetDescriptor() =>
        new(
            ProviderKey,
            DisplayName: ProviderKey,
            RequiredCredentialKeys: []);

    public Task<ShippingProviderConnectionHealth> TestConnectionAsync(
        ShippingProviderConnectionContext context,
        CancellationToken cancellationToken = default) =>
        Task.FromResult(new ShippingProviderConnectionHealth(
            Status: ShippingConnectionHealthStatus.Healthy,
            LastChecked: DateTimeOffset.UnixEpoch,
            Message: null));

    public Task<IReadOnlyList<ProviderShippingRate>> GetRatesAsync(
        ShippingProviderConnectionContext context,
        ProviderRateRequest request,
        CancellationToken cancellationToken = default)
    {
        Interlocked.Increment(ref _rateCallCount);

        return ExceptionToThrow is null
            ? Task.FromResult(Rates)
            : Task.FromException<IReadOnlyList<ProviderShippingRate>>(ExceptionToThrow);
    }

    public ProviderRateCachePolicy GetRateCachePolicy() => CachePolicy;
}
