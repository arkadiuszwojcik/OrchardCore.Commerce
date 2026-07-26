using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Descriptor metadata for a shipping provider (name, capabilities, required credentials).
/// </summary>
public sealed record ShippingProviderDescriptor(
    string ProviderId,
    string DisplayName,
    ShippingProviderCapabilities Capabilities,
    IReadOnlyList<string> RequiredCredentialKeys,
    string? ProviderIconUrl = null,
    string? DocumentationUrl = null,
    IDictionary<string, object>? Metadata = null);
