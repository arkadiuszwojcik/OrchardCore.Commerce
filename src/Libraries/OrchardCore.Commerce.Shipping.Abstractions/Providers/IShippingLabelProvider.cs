using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Provider capability for generating shipping labels.
/// </summary>
public interface IShippingLabelProvider : IShippingProvider
{
    /// <summary>
    /// Retrieves the label for an existing shipment.
    /// </summary>
    Task<byte[]> GetLabelAsync(
        ShippingProviderConnectionContext context,
        string shipmentId,
        string? format = null,
        CancellationToken cancellationToken = default);
}
