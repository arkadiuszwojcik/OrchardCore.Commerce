using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Service for testing provider connection health.
/// </summary>
public interface IShippingProviderConnectionHealthService
{
    /// <summary>
    /// Tests the health of a provider connection.
    /// </summary>
    Task<ShippingProviderConnectionHealth> TestConnectionAsync(
        string providerConnectionId,
        CancellationToken cancellationToken = default);
}
