using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Metadata about a service offered by a carrier.
/// </summary>
public sealed record ShippingServiceDescriptor(
    string ServiceId,
    string ServiceName,
    string? Description = null,
    bool IsInternational = false,
    bool RequiresSignature = false,
    IDictionary<string, object>? Metadata = null);

/// <summary>
/// Provider capability for listing available shipping services.
/// </summary>
public interface IShippingServiceCatalogProvider : IShippingProvider
{
    /// <summary>
    /// Lists all services offered by this provider.
    /// </summary>
    Task<IReadOnlyList<ShippingServiceDescriptor>> GetAvailableServicesAsync(
        ShippingProviderConnectionContext context,
        CancellationToken cancellationToken = default);
}
