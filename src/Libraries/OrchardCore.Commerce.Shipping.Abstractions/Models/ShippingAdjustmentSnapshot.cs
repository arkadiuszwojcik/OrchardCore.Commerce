using OrchardCore.Commerce.MoneyDataType;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Snapshot of a price adjustment (markup, discount, tax) applied to a shipping quote.
/// </summary>
public sealed record ShippingAdjustmentSnapshot(
    string AdjustmentType,
    string? DisplayName,
    Amount Amount,
    string? Formula = null);
