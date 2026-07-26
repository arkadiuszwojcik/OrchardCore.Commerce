namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// How to validate quotes before allowing checkout.
/// </summary>
public enum ShippingQuoteValidationMode
{
    /// <summary>
    /// Do not validate; accept any quote.
    /// </summary>
    None,

    /// <summary>
    /// Validate that the quote is still available and price has not changed.
    /// </summary>
    ValidatePrice,

    /// <summary>
    /// Re-fetch quote and compare all details (price, delivery estimate, etc.).
    /// </summary>
    RefetchAndCompare,
}
