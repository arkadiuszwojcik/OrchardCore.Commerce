namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX sender/receiver shape, grounded in <c>ShipX_Shipment_Sender_Model</c> (WooCommerce plugin):
/// <c>first_name</c>, <c>last_name</c>, <c>company_name</c>, <c>email</c>, <c>phone</c>, <c>address</c>.
/// </summary>
public sealed record InPostContact(
    string FirstName,
    string LastName,
    string Email,
    string Phone,
    InPostAddress Address,
    string? CompanyName = null);
