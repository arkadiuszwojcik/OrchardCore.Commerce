using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Opaque data blob returned by a provider extension (e.g., validated address, insurance receipt).
/// </summary>
public sealed record ShippingExtensionData(
    string ExtensionId,
    IDictionary<string, object>? Data = null);
