using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// One page of a shipment search. <see cref="Count"/>, <see cref="Page"/> and <see cref="PerPage"/> come from the ShipX
/// collection envelope and are null when the response was a bare array.
/// </summary>
public sealed record InPostShipmentPage(
    IReadOnlyList<InPostShipmentResponse> Items,
    int? Count,
    int? Page,
    int? PerPage);
