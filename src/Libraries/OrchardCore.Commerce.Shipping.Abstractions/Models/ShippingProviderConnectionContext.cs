using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Runtime connection context passed to a provider (credentials, normalization profile).
/// </summary>
public sealed record ShippingProviderConnectionContext(
    string ConnectionId,
    IDictionary<string, string> Credentials,
    ShippingNormalizationProfile? NormalizationProfile = null,
    IDictionary<string, object>? Metadata = null,
    string? ProviderKey = null,
    long ConfigurationRevision = 0,
    long CredentialVersion = 0,
    bool IsTestMode = false);
