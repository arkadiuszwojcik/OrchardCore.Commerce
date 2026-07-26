namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Health status of a provider connection (credential test result).
/// </summary>
public enum ShippingConnectionHealthStatus
{
    Unknown,
    Healthy,
    Degraded,
    Unhealthy,
}
