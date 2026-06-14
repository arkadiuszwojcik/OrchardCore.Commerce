namespace OrchardCore.Commerce.Shipping.Models;

public sealed record ShippingRateSelectionRequest(
    string ShoppingCartId,
    string Provider,
    string MethodCode,
    decimal Price,
    string Currency,
    string? DisplayName = null,
    string? ExternalMethodCode = null);