namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX address shape.
/// </summary>
public sealed record InPostAddress(
    long? Id,
    string? Street,
    string? BuildingNumber,
    string? Line1,
    string? Line2,
    string? City,
    string? PostCode,
    string? CountryCode);
