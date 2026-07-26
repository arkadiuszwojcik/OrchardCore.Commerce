using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Temporary storage for shipping quotes during checkout.
/// </summary>
public interface IShippingQuoteStore
{
    /// <summary>
    /// Stores a shipping quote.
    /// </summary>
    Task StoreQuoteAsync(ShippingQuote quote, CancellationToken cancellationToken = default);

    /// <summary>
    /// Retrieves a stored quote by ID.
    /// </summary>
    Task<ShippingQuote?> GetQuoteAsync(string quoteId, CancellationToken cancellationToken = default);

    /// <summary>
    /// Deletes a quote (after order completion or expiry).
    /// </summary>
    Task DeleteQuoteAsync(string quoteId, CancellationToken cancellationToken = default);
}
