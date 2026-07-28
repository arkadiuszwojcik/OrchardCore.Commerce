namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX parcel shape, grounded in <c>ShipX_Shipment_Parcel_Model</c> (WooCommerce plugin):
/// <c>id</c>, <c>template</c>, <c>dimensions</c>, <c>weight</c>, <c>is_non_standard</c>.
/// </summary>
public sealed record InPostParcel(
    InPostWeight Weight,
    string? Id = null,
    string? Template = null,
    InPostDimensions? Dimensions = null,
    bool IsNonStandard = false);
