namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Webhook endpoint information provided by a provider for tracking/event notifications.
/// </summary>
public sealed record ShippingWebhookEndpoint(
    string WebhookUrl,
    string? WebhookSecret = null);
