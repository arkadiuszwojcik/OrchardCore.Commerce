using System.Text.Json;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// Subset of the ShipX shipment resource fields this module consumes, grounded in the <c>Shipment</c> resource
/// fields observed in both PHP plugins: <c>id</c>, <c>status</c>, <c>tracking_number</c>, <c>service</c>.
/// <c>Offers</c>/<c>SelectedOffer</c> are kept as raw <see cref="JsonElement"/> because their exact nested shape
/// (price/currency/courier fields returned by the <c>/calculate</c> endpoint) was not directly observable in the
/// PHP source and is parsed defensively by the caller.
/// </summary>
public sealed record InPostShipmentResponse(
    long? Id,
    string? Status,
    string? TrackingNumber,
    string? Service,
    JsonElement? Offers,
    JsonElement? SelectedOffer);
