using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// Request body for <c>POST /v1/organizations/{organizationId}/shipments</c> and
/// <c>POST /v1/organizations/{organizationId}/shipments/calculate</c>, grounded in the shipment payload shapes
/// used across both PHP plugins.
/// </summary>
public sealed record InPostShipmentRequest(
    InPostContact Receiver,
    IReadOnlyList<InPostParcel> Parcels,
    string Service,
    InPostCustomAttributes CustomAttributes,
    InPostContact? Sender = null,
    string? Reference = null,
    InPostMoney? Cod = null,
    InPostMoney? Insurance = null);
