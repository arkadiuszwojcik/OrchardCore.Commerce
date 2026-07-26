using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Estimated delivery time range.
/// </summary>
public sealed record DeliveryEstimate(
    DateTimeOffset? EarliestDelivery = null,
    DateTimeOffset? LatestDelivery = null,
    int? BusinessDays = null)
{
    /// <summary>
    /// Single-day estimate.
    /// </summary>
    public static DeliveryEstimate FromDate(DateTimeOffset date) =>
        new(date, date, null);

    /// <summary>
    /// Business-day estimate (e.g., "3-5 business days").
    /// </summary>
    public static DeliveryEstimate FromBusinessDays(int days) =>
        new(null, null, days);
}
