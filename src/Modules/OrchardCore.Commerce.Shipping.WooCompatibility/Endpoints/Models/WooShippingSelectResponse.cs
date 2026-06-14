namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public sealed record WooShippingSelectResponse(
    bool Success,
    string? Error = null,
    string? ShoppingCartId = null,
    string? MethodId = null,
    decimal? Cost = null,
    string? Currency = null);