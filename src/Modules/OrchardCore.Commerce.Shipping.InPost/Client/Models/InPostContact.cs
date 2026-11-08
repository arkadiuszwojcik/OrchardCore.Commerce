namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

public sealed record InPostContact(
    string FirstName,
    string LastName,
    string Email,
    string Phone,
    InPostAddress Address,
    string? CompanyName = null);
