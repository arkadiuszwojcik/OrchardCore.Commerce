using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Resolves weight and dimensions for products (from content items or product catalog).
/// </summary>
public interface IShippingPhysicalDataResolver
{
    /// <summary>
    /// Gets the weight for a product.
    /// </summary>
    Task<Weight?> GetWeightAsync(string productId, CancellationToken cancellationToken = default);

    /// <summary>
    /// Gets the dimensions for a product.
    /// </summary>
    Task<Dimensions?> GetDimensionsAsync(string productId, CancellationToken cancellationToken = default);
}
