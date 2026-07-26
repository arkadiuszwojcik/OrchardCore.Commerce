using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Immutable snapshot of a shipping address.
/// </summary>
public sealed record AddressSnapshot(
    string? Name = null,
    string? Company = null,
    string? Line1 = null,
    string? Line2 = null,
    string? City = null,
    string? Province = null,
    string? PostalCode = null,
    string? CountryCode = null,
    string? Phone = null,
    string? Email = null,
    IDictionary<string, string>? Metadata = null);
