namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Normalization settings for address/weight/dimension conversion when calling providers.
/// </summary>
public sealed record ShippingNormalizationProfile(
    WeightUnit PreferredWeightUnit = WeightUnit.Kilogram,
    DimensionUnit PreferredDimensionUnit = DimensionUnit.Centimeter,
    bool NormalizeAddressFormat = true);
