using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Base provider interface - all shipping providers must implement this.
/// </summary>
public interface IShippingProvider
{
    /// <summary>
    /// Unique identifier for this provider (e.g., "DHL", "FedEx").
    /// </summary>
    string ProviderId { get; }

    /// <summary>
    /// Gets the descriptor metadata for this provider.
    /// </summary>
    ShippingProviderDescriptor GetDescriptor();

    /// <summary>
    /// Tests the connection health with the given credentials.
    /// </summary>
    Task<ShippingProviderConnectionHealth> TestConnectionAsync(
        ShippingProviderConnectionContext context,
        CancellationToken cancellationToken = default);
}
