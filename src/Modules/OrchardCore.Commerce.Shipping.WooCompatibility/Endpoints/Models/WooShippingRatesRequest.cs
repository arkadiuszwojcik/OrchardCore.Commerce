namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public class WooShippingRatesRequest
{
    public string? ShoppingCartId { get; set; }

    public WooAddressDto? ShippingAddress { get; set; }

    public decimal CartSubtotal { get; set; }

    public string Currency { get; set; } = "PLN";
}