#nullable enable
using Microsoft.Extensions.Caching.Distributed;
using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// <see cref="IDistributedCache"/>-backed implementation of <see cref="IShippingDocumentStore"/>.
/// Documents (labels, customs forms) are stored as raw bytes keyed by a generated document ID.
/// </summary>
public class ShippingDocumentStore : IShippingDocumentStore
{
    private const string KeyPrefix = "ShippingDoc:";

    // Shipping labels should be available for the life of the shipment — 30 days is a reasonable default.
    private static readonly TimeSpan DefaultDocumentExpiry = TimeSpan.FromDays(30);

    private readonly IDistributedCache _cache;

    public ShippingDocumentStore(IDistributedCache cache) => _cache = cache;

    public async Task<string> StoreDocumentAsync(
        string shipmentId,
        string documentType,
        byte[] documentData,
        string? fileName = null,
        CancellationToken cancellationToken = default)
    {
        var documentId = $"{shipmentId}:{documentType}:{Guid.NewGuid():N}";
        var options = new DistributedCacheEntryOptions
        {
            AbsoluteExpirationRelativeToNow = DefaultDocumentExpiry,
        };

        await _cache.SetAsync(KeyPrefix + documentId, documentData, options, cancellationToken);

        return documentId;
    }

    public Task<byte[]?> GetDocumentAsync(
        string documentId,
        CancellationToken cancellationToken = default) =>
        _cache.GetAsync(KeyPrefix + documentId, cancellationToken)!;

    public Task DeleteDocumentAsync(
        string documentId,
        CancellationToken cancellationToken = default) =>
        _cache.RemoveAsync(KeyPrefix + documentId, cancellationToken);
}
