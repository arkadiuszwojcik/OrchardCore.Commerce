using OrchardCore.Commerce.Shipping.Models;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

public interface IShippingService
{
    Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
        ShippingRateRequest request,
        CancellationToken cancellationToken = default);

    Task<ShippingRateSelectionResult> SelectRateAsync(
        ShippingRateSelectionRequest request,
        CancellationToken cancellationToken = default);

    Task<ShippingSelection?> GetSelectionAsync(
        string shoppingCartId,
        CancellationToken cancellationToken = default);
}