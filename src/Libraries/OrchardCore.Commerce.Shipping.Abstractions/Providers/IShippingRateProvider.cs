using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Provider capability for fetching real-time shipping rates.
/// </summary>
public interface IShippingRateProvider : IShippingProvider
{
    /// <summary>
    /// Retrieves available shipping rates for the given request.
    /// </summary>
    Task<IReadOnlyList<ProviderShippingRate>> GetRatesAsync(
        ShippingProviderConnectionContext context,
        ProviderRateRequest request,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Gets the cache policy for rate requests.
    /// </summary>
    ProviderRateCachePolicy GetRateCachePolicy();
}
