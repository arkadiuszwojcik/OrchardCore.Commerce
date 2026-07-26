using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Resolves stable product identifiers from legacy order line data (for shipment reconciliation).
/// </summary>
public interface ILegacyOrderLineIdentityResolver
{
    /// <summary>
    /// Resolves a stable product ID from an order line (handles SKU changes, content item migrations).
    /// </summary>
    Task<string?> ResolveProductIdAsync(
        string orderLineId,
        string? sku,
        CancellationToken cancellationToken = default);
}
