namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public sealed record WooShippingSelectRequest(
    string ShoppingCartId,
    string Provider,
    string MethodId,
    decimal Cost,
    string Currency,
    string? MethodTitle = null,
    string? InternalMethodCode = null);