namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX parcel weight, grounded in <c>ShipX_Shipment_Parcel_Weight_Model</c> (WooCommerce plugin).
/// The unit defaults to kilograms, matching the PHP model's default (<c>$unit = 'kg'</c>).
/// </summary>
public sealed record InPostWeight(decimal Amount, string Unit = "kg");
