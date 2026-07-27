using OrchardCore.Commerce.MoneyDataType;
using OrchardCore.Commerce.Shipping.Abstractions;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Zero-tax stub implementation of <see cref="IShippingTaxService"/>.
/// Tax on shipping is jurisdiction- and carrier-specific; replace this with a real
/// implementation (e.g. integrate with the Tax module) when needed.
/// </summary>
public class ShippingTaxService : IShippingTaxService
{
    public Task<Amount> CalculateTaxAsync(
        ShippingQuote quote,
        AddressSnapshot destinationAddress,
        CancellationToken cancellationToken = default) =>
        Task.FromResult(new Amount(0m, quote.TotalPrice.Currency));
}
