using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.ContentManagement;
using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Loads a <see cref="ShippingProviderConnectionPart"/> content item and delegates to
/// the matching provider's <see cref="IShippingProvider.TestConnectionAsync"/>.
/// </summary>
public class ShippingProviderConnectionHealthService : IShippingProviderConnectionHealthService
{
    private readonly IContentManager _contentManager;
    private readonly IShippingProviderRegistry _providerRegistry;

    public ShippingProviderConnectionHealthService(
        IContentManager contentManager,
        IShippingProviderRegistry providerRegistry)
    {
        _contentManager = contentManager;
        _providerRegistry = providerRegistry;
    }

    public async Task<ShippingProviderConnectionHealth> TestConnectionAsync(
        string providerConnectionId,
        CancellationToken cancellationToken = default)
    {
        var connectionItem = await _contentManager.GetAsync(providerConnectionId);
        if (connectionItem is null)
        {
            return new ShippingProviderConnectionHealth(
                ShippingConnectionHealthStatus.Unknown,
                DateTimeOffset.UtcNow,
                "Connection content item not found.");
        }

        if (!connectionItem.TryGet<ShippingProviderConnectionPart>(out var part) || string.IsNullOrEmpty(part.ProviderId.Text))
        {
            return new ShippingProviderConnectionHealth(
                ShippingConnectionHealthStatus.Unknown,
                DateTimeOffset.UtcNow,
                "No provider ID configured on connection.");
        }

        var provider = _providerRegistry.GetProvider(part.ProviderId.Text);
        if (provider is null)
        {
            return new ShippingProviderConnectionHealth(
                ShippingConnectionHealthStatus.Unknown,
                DateTimeOffset.UtcNow,
                $"Provider '{part.ProviderId.Text}' is not registered.");
        }

        var credentials = new Dictionary<string, string>();
        if (!string.IsNullOrWhiteSpace(part.CredentialsJson.Text))
        {
            try
            {
                var parsed = System.Text.Json.JsonSerializer
                    .Deserialize<Dictionary<string, string>>(part.CredentialsJson.Text);
                if (parsed is not null) credentials = parsed;
            }
            catch { /* ignore malformed JSON — provider will surface the error */ }
        }

        var context = new ShippingProviderConnectionContext(providerConnectionId, credentials);
        return await provider.TestConnectionAsync(context, cancellationToken);
    }
}
