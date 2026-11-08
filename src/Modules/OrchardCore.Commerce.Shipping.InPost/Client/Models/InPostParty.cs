namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

public sealed record InPostParty(
    string FirstName,
    string LastName,
    string Email,
    string Phone,
    InPostAddress Address,
    string? CompanyName = null);
