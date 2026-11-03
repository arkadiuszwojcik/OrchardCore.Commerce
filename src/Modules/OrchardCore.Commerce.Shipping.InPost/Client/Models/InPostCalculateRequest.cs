using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

public record InPostCalculateRequest(IReadOnlyList<InPostShipmentRequest> Shipments);
