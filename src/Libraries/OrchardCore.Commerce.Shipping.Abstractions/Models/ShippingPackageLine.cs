using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A single line item within a shipping package.
/// </summary>
public sealed record ShippingPackageLine(
    string ProductId,
    string? Sku = null,
    string? Name = null,
    int Quantity = 1,
    Weight? Weight = null,
    Dimensions? Dimensions = null,
    IDictionary<string, object>? Metadata = null);
