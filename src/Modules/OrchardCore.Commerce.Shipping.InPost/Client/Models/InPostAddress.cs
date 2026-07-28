namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX address shape, grounded in the InPost PHP plugins' address models
/// (<c>street</c>, <c>building_number</c>, <c>city</c>, <c>post_code</c>, <c>country_code</c>).
/// </summary>
public sealed record InPostAddress(
    string Street,
    string BuildingNumber,
    string City,
    string PostCode,
    string CountryCode);
