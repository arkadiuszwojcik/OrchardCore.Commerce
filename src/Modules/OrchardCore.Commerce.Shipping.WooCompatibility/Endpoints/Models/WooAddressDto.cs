namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public class WooAddressDto
{
    public string? Name { get; set; }

    public string? Company { get; set; }

    public string? Street1 { get; set; }

    public string? Street2 { get; set; }

    public string? City { get; set; }

    public string? Province { get; set; }

    public string? PostalCode { get; set; }

    public string? Region { get; set; }
}