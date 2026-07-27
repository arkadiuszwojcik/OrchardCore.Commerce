#nullable enable
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.ContentManagement;
using System;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Resolves physical weight and dimensions for a product content item.
/// Looks for a <c>ShippingPhysicalPart</c> attached to the product. Returns <see langword="null"/>
/// when the content item does not exist or does not carry physical data, allowing the packer
/// to fall back to the module defaults.
/// </summary>
public class ShippingPhysicalDataResolver : IShippingPhysicalDataResolver
{
    private readonly IContentManager _contentManager;
    private readonly ShippingOptions _options;

    public ShippingPhysicalDataResolver(
        IContentManager contentManager,
        IOptionsSnapshot<ShippingOptions> options)
    {
        _contentManager = contentManager;
        _options = options.Value;
    }

    public async Task<Weight?> GetWeightAsync(string productId, CancellationToken cancellationToken = default)
    {
        var part = await GetPhysicalPartAsync(productId);
        if (part is null) return null;

        var value = part.Get<ContentField>("WeightValue")
            ?.Content?["Value"]?.GetValue<decimal?>();
        var unitText = part.Get<ContentField>("WeightUnit")
            ?.Content?["Text"]?.GetValue<string?>();

        if (value is null || value <= 0) return null;

        WeightUnit unit = _options.DefaultWeightUnit;
        if (unitText is not null)
        {
            WeightUnit parsedWeight;
            if (Enum.TryParse<WeightUnit>(unitText, true, out parsedWeight))
            {
                unit = parsedWeight;
            }
        }

        return new Weight(value!.Value, unit);
    }

    public async Task<Dimensions?> GetDimensionsAsync(string productId, CancellationToken cancellationToken = default)
    {
        var part = await GetPhysicalPartAsync(productId);
        if (part is null) return null;

        var length = part.Get<ContentField>("Length")?.Content?["Value"]?.GetValue<decimal?>();
        var width = part.Get<ContentField>("Width")?.Content?["Value"]?.GetValue<decimal?>();
        var height = part.Get<ContentField>("Height")?.Content?["Value"]?.GetValue<decimal?>();
        var unitText = part.Get<ContentField>("DimensionUnit")?.Content?["Text"]?.GetValue<string?>();

        if (length is null || width is null || height is null
            || length <= 0 || width <= 0 || height <= 0)
        {
            return null;
        }

        DimensionUnit unit = _options.DefaultDimensionUnit;
        if (unitText is not null)
        {
            DimensionUnit parsedDim;
            if (Enum.TryParse<DimensionUnit>(unitText, true, out parsedDim))
            {
                unit = parsedDim;
            }
        }

        return new Dimensions(length!.Value, width!.Value, height!.Value, unit);
    }

    private async Task<ContentPart?> GetPhysicalPartAsync(string productId)
    {
        if (string.IsNullOrEmpty(productId)) return null;

        var contentItem = await _contentManager.GetAsync(productId);
        return contentItem?.Get<ContentPart>("ShippingPhysicalPart");
    }
}
