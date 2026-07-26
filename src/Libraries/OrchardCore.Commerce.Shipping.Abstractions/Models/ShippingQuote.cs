using OrchardCore.Commerce.MoneyDataType;
using System;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A customer-facing shipping quote (rate + markup + adjustments), stored temporarily during checkout.
/// </summary>
public sealed record ShippingQuote(
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
    DateTimeOffset? ExpiresAt = null,
    IReadOnlyList<ShippingExtensionData>? ExtensionData = null,
    IReadOnlyList<ShippingQuoteAuditEntry>? AuditTrail = null,
    IDictionary<string, object>? Metadata = null);
