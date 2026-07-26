using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Line item to be packed (from cart or order).
/// </summary>
public sealed record ShippingPackingLine(
    string ProductId,
    string? Sku,
    string? Name,
    int Quantity,
    Weight? Weight,
    Dimensions? Dimensions);

/// <summary>
/// Service for packing items into shippable packages.
/// </summary>
public interface IShippingPackerService
{
    /// <summary>
    /// Packs line items into one or more packages.
    /// </summary>
    Task<IReadOnlyList<ShippingPackage>> PackAsync(
        IReadOnlyList<ShippingPackingLine> lines,
        CancellationToken cancellationToken = default);
}
