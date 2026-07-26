using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A shipping manifest (batch of shipments for carrier pickup).
/// </summary>
public sealed record ShippingManifest(
    string ManifestId,
    IReadOnlyList<string> ShipmentIds,
    DateTimeOffset CreatedAt,
    byte[]? ManifestDocument = null,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Provider capability for creating manifests/end-of-day reports.
/// </summary>
public interface IShippingManifestProvider : IShippingProvider
{
    /// <summary>
    /// Creates a manifest (end-of-day report) for a batch of shipments.
    /// </summary>
    Task<ShippingManifest> CreateManifestAsync(
        ShippingProviderConnectionContext context,
        IReadOnlyList<string> shipmentIds,
        CancellationToken cancellationToken = default);
}
