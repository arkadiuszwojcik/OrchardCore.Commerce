using OrchardCore.Commerce.AddressDataType;

namespace OrchardCore.Commerce.Shipping.Models;

public sealed record ShippingRateRequest(
    string? ShoppingCartId,
    Address? ShippingAddress,
    decimal CartSubtotal,
    string Currency);