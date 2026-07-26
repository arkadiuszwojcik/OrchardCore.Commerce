using OrchardCore.Commerce.MoneyDataType;
using System;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Immutable snapshot of the selected shipping quote stored in an order.
/// </summary>
public sealed record SelectedShippingQuoteSnapshot(
    string QuoteId,
    string ShippingMethodId,
    string ProviderConnectionId,
    string ServiceId,
    string ServiceName,
    Amount TotalPrice,
    IReadOnlyList<ShippingCharge> Charges,
    DeliveryEstimate? DeliveryEstimate = null,
    AddressSnapshot? OriginAddress = null,
    AddressSnapshot? DestinationAddress = null,
    PickupPointSnapshot? PickupPoint = null,
    DateTimeOffset SelectedAt = default,
    IReadOnlyList<ShippingAdjustmentSnapshot>? Adjustments = null,
    IReadOnlyList<ShippingExtensionData>? ExtensionData = null,
    IDictionary<string, object>? Metadata = null);
