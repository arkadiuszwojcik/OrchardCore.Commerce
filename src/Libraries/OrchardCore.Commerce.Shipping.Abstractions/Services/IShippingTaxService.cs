using OrchardCore.Commerce.MoneyDataType;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Service for calculating tax on shipping charges.
/// </summary>
public interface IShippingTaxService
{
    /// <summary>
    /// Calculates tax for a shipping quote.
    /// </summary>
    Task<Amount> CalculateTaxAsync(
        ShippingQuote quote,
        AddressSnapshot destinationAddress,
        CancellationToken cancellationToken = default);
}
