using OrchardCore.Commerce.MoneyDataType;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A single shipping rate returned by a provider (before markup/adjustments).
/// </summary>
public sealed record ProviderShippingRate(
    string ServiceId,
    string ServiceName,
    Amount BasePrice,
    IReadOnlyList<ShippingCharge> Charges,
    DeliveryEstimate? DeliveryEstimate = null,
    bool RequiresSignature = false,
    string? ProviderReferenceId = null,
    string? ProviderTrackingUrl = null,
    IReadOnlyList<ShippingExtensionData>? ExtensionData = null,
    IDictionary<string, object>? Metadata = null);
