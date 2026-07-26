using OrchardCore.Commerce.MoneyDataType;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A single line-item charge within a shipping quote (base rate, surcharge, fuel, insurance, etc.).
/// </summary>
public sealed record ShippingCharge(
    string Name,
    Amount Amount,
    ShippingTaxMode TaxMode = ShippingTaxMode.Net);
