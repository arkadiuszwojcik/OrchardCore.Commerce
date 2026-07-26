using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Configuration describing an optional provider-specific extension (e.g., address validation, insurance).
/// </summary>
public sealed record ShippingExtensionConfiguration(
    string ExtensionId,
    string DisplayName,
    bool IsEnabled = false,
    IDictionary<string, object>? Settings = null);
