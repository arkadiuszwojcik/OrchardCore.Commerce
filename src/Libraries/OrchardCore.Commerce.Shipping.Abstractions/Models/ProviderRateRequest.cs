using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Request sent to a provider to fetch real-time shipping rates.
/// </summary>
public sealed record ProviderRateRequest(
    string ProviderConnectionId,
    AddressSnapshot OriginAddress,
    AddressSnapshot DestinationAddress,
    IReadOnlyList<ShippingPackage> Packages,
    string? PickupPointId = null,
    IReadOnlyList<ShippingExtensionConfiguration>? Extensions = null,
    IDictionary<string, object>? Metadata = null);
