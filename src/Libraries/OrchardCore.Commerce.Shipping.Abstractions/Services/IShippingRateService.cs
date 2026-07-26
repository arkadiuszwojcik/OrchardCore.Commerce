using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Core service for fetching and managing shipping rates.
/// </summary>
public interface IShippingRateService
{
    /// <summary>
    /// Gets available shipping rates for a cart/order.
    /// </summary>
    Task<IReadOnlyList<ShippingQuote>> GetAvailableRatesAsync(
        string shippingMethodId,
        AddressSnapshot originAddress,
        AddressSnapshot destinationAddress,
        IReadOnlyList<ShippingPackage> packages,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Validates a previously retrieved quote (checks expiry, price changes).
    /// </summary>
    Task<bool> ValidateQuoteAsync(
        string quoteId,
        ShippingQuoteValidationMode validationMode,
        CancellationToken cancellationToken = default);
}
