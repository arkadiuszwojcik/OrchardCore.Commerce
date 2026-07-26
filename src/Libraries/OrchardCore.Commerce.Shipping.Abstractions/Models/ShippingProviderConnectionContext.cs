using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Runtime connection context passed to a provider (credentials, normalization profile).
/// </summary>
public sealed record ShippingProviderConnectionContext(
    string ConnectionId,
    IDictionary<string, string> Credentials,
    ShippingNormalizationProfile? NormalizationProfile = null,
    IDictionary<string, object>? Metadata = null);
