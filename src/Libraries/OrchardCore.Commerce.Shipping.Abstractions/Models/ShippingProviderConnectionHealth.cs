using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Health check result for a provider connection.
/// </summary>
public sealed record ShippingProviderConnectionHealth(
    ShippingConnectionHealthStatus Status,
    DateTimeOffset LastChecked,
    string? Message = null);
