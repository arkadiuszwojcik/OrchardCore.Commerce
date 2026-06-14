using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

public class FlatRateShippingProvider : IShippingRateProvider
{
    public int Order => int.MaxValue;

    public Task<bool> IsApplicableAsync(ShippingRateRequest request, CancellationToken cancellationToken = default) =>
        Task.FromResult(!string.IsNullOrWhiteSpace(request.Currency));

    public Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
        ShippingRateRequest request,
        CancellationToken cancellationToken = default)
    {
        IReadOnlyList<ShippingRateOption> rates =
        [
            new ShippingRateOption(
                Provider: "FlatRate",
                MethodCode: "flat_standard",
                DisplayName: "Standard Shipping",
                Price: 12.99m,
                Currency: request.Currency,
                Description: "Fallback shipping method for environments without a carrier provider.",
                ExternalMethodCode: "flat_rate")
        ];

        return Task.FromResult(rates);
    }
}