using OrchardCore.Commerce.Shipping.Abstractions;

namespace OrchardCore.Commerce.Shipping;

/// <summary>
/// Tenant-wide shipping configuration.
/// </summary>
public class ShippingOptions
{
    /// <summary>
    /// Default weight unit for products without explicit weight.
    /// </summary>
    public WeightUnit DefaultWeightUnit { get; set; } = WeightUnit.Kilogram;

    /// <summary>
    /// Default dimension unit for products without explicit dimensions.
    /// </summary>
    public DimensionUnit DefaultDimensionUnit { get; set; } = DimensionUnit.Centimeter;

    /// <summary>
    /// How long shipping quotes remain valid (in minutes).
    /// </summary>
    public int QuoteExpirationMinutes { get; set; } = 30;

    /// <summary>
    /// Validation mode for quotes during checkout.
    /// </summary>
    public ShippingQuoteValidationMode QuoteValidationMode { get; set; } = ShippingQuoteValidationMode.ValidatePrice;

    /// <summary>
    /// Enable automatic tracking updates via webhooks.
    /// </summary>
    public bool EnableTrackingWebhooks { get; set; } = true;

    /// <summary>
    /// Enable provider rate caching.
    /// </summary>
    public bool EnableRateCaching { get; set; } = true;

    /// <summary>
    /// Default cache duration for provider rates (in minutes).
    /// </summary>
    public int DefaultRateCacheDurationMinutes { get; set; } = 15;

    public void CopyTo(ShippingOptions target)
    {
        target.DefaultWeightUnit = DefaultWeightUnit;
        target.DefaultDimensionUnit = DefaultDimensionUnit;
        target.QuoteExpirationMinutes = QuoteExpirationMinutes;
        target.QuoteValidationMode = QuoteValidationMode;
        target.EnableTrackingWebhooks = EnableTrackingWebhooks;
        target.EnableRateCaching = EnableRateCaching;
        target.DefaultRateCacheDurationMinutes = DefaultRateCacheDurationMinutes;
    }
}
