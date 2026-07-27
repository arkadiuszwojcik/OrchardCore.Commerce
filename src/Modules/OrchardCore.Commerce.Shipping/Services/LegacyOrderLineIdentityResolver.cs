#nullable enable
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.ContentManagement;
using OrchardCore.ContentManagement.Records;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;
using YesSql;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Resolves a stable content-item product ID from an order line's SKU.
/// Uses the <see cref="ContentItemIndex"/> to look up the most recent published content item
/// whose SKU field matches.
/// </summary>
public class LegacyOrderLineIdentityResolver : ILegacyOrderLineIdentityResolver
{
    private readonly ISession _session;

    public LegacyOrderLineIdentityResolver(ISession session) => _session = session;

    public async Task<string?> ResolveProductIdAsync(
        string orderLineId,
        string? sku,
        CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrWhiteSpace(sku)) return null;

        // Find a published content item whose DisplayText or alias matches the SKU.
        // The ProductPartIndex stores SKU under the DisplayText column when set.
        var result = await _session
            .Query<ContentItem, ContentItemIndex>(index =>
                index.Published &&
                index.DisplayText == sku)
            .FirstOrDefaultAsync();

        return result?.ContentItemId;
    }
}
