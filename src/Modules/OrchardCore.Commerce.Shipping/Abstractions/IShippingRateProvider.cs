using OrchardCore.Commerce.Shipping.Models;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

public interface IShippingRateProvider
{
    int Order { get; }

    Task<bool> IsApplicableAsync(ShippingRateRequest request, CancellationToken cancellationToken = default);

    Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
        ShippingRateRequest request,
        CancellationToken cancellationToken = default);
}