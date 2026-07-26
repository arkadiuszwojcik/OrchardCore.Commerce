using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Context for an incoming webhook.
/// </summary>
public sealed record ShippingWebhookContext(
    string ProviderId,
    string WebhookEventType,
    string RawPayload,
    string? Signature = null);

/// <summary>
/// Provider capability for handling webhooks (tracking updates, etc.).
/// </summary>
public interface IShippingWebhookHandler : IShippingProvider
{
    /// <summary>
    /// Processes an incoming webhook from the carrier.
    /// </summary>
    Task<bool> HandleWebhookAsync(
        ShippingProviderConnectionContext context,
        ShippingWebhookContext webhook,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Returns webhook endpoint info for registration with the carrier.
    /// </summary>
    ShippingWebhookEndpoint GetWebhookEndpoint();
}
