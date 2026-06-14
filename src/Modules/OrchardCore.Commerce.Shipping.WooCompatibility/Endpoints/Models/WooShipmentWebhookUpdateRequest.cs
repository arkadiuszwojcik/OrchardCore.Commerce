using System;

namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public class WooShipmentWebhookUpdateRequest
{
    public string? OrderId { get; set; }

    public string? ShipmentId { get; set; }

    public string? Status { get; set; }

    public DateTimeOffset? UpdatedAt { get; set; }
}