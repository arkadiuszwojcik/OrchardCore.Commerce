namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX parcel dimensions, grounded in <c>ShipX_Shipment_Parcel_Dimensions_Model</c> (WooCommerce plugin).
/// The unit defaults to millimeters, matching the PHP model's default (<c>$unit = 'mm'</c>).
/// </summary>
public sealed record InPostDimensions(decimal Length, decimal Width, decimal Height, string Unit = "mm");
