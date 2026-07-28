namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX money shape used for <c>cod</c> and <c>insurance</c>, grounded in <c>ShipX_Shipment_Cod_Model</c> and
/// <c>ShipX_Shipment_Insurance_Model</c> (WooCommerce plugin): <c>amount</c>, <c>currency</c>.
/// </summary>
public sealed record InPostMoney(decimal Amount, string Currency);
