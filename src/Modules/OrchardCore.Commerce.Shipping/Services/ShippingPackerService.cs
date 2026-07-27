#nullable enable
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Naive single-package implementation of <see cref="IShippingPackerService"/>.
/// All lines are placed into one package; total weight is summed and the maximum
/// dimension across all lines is used as the bounding box.
/// </summary>
public class ShippingPackerService : IShippingPackerService
{
    private readonly ShippingOptions _options;

    public ShippingPackerService(IOptionsSnapshot<ShippingOptions> options) =>
        _options = options.Value;

    public Task<IReadOnlyList<ShippingPackage>> PackAsync(
        IReadOnlyList<ShippingPackingLine> lines,
        CancellationToken cancellationToken = default)
    {
        if (lines.Count == 0)
        {
            return Task.FromResult<IReadOnlyList<ShippingPackage>>(Array.Empty<ShippingPackage>());
        }

        var weightUnit = _options.DefaultWeightUnit;
        var dimUnit = _options.DefaultDimensionUnit;

        // Sum total weight across all lines, normalizing to the configured default unit.
        var totalWeightValue = lines.Sum(line =>
        {
            if (line.Weight is null) return 0m;
            return line.Weight.ConvertTo(weightUnit).Value * line.Quantity;
        });

        var totalWeight = new Weight(totalWeightValue, weightUnit);

        // Bounding-box dimensions: max length, width, height across all lines.
        var dimensionLines = lines
            .Where(l => l.Dimensions is not null)
            .Select(l => l.Dimensions!.ConvertTo(dimUnit))
            .ToList();

        Dimensions? boundingBox = dimensionLines.Count > 0
            ? new Dimensions(
                dimensionLines.Max(d => d.Length),
                dimensionLines.Max(d => d.Width),
                dimensionLines.Max(d => d.Height),
                dimUnit)
            : null;

        var packageLines = lines
            .Select(l => new ShippingPackageLine(
                l.ProductId,
                l.Sku,
                l.Name,
                l.Quantity,
                l.Weight,
                l.Dimensions))
            .ToList();

        var package = new ShippingPackage(
            PackageId: Guid.NewGuid().ToString("N"),
            Lines: packageLines,
            TotalWeight: totalWeight,
            Dimensions: boundingBox);

        return Task.FromResult<IReadOnlyList<ShippingPackage>>([package]);
    }
}
