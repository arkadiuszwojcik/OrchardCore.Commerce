namespace OrchardCore.Commerce.Shipping.Models;

public sealed record ShippingRateOption(
    string Provider,
    string MethodCode,
    string DisplayName,
    decimal Price,
    string Currency,
    string? Description = null,
    string? ExternalMethodCode = null);