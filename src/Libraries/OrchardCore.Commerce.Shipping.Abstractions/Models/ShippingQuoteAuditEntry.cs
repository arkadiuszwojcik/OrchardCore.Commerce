using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Single audit entry recording a change to a shipping quote (e.g., price change, validation).
/// </summary>
public sealed record ShippingQuoteAuditEntry(
    DateTimeOffset Timestamp,
    string Action,
    string? Details = null);
