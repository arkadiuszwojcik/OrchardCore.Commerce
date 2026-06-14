using OrchardCore.Commerce.Shipping.Models;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Abstractions;

public interface IInPostShippingClient
{
    Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
        ShippingRateRequest request,
        CancellationToken cancellationToken = default);
}