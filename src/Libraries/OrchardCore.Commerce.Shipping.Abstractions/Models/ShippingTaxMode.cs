namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// How tax should be considered in shipping charges.
/// </summary>
public enum ShippingTaxMode
{
    /// <summary>
    /// Shipping price is net; tax should be calculated separately.
    /// </summary>
    Net,

    /// <summary>
    /// Shipping price already includes tax.
    /// </summary>
    Gross,

    /// <summary>
    /// Shipping tax is not applicable or provider does not specify.
    /// </summary>
    None,
}
