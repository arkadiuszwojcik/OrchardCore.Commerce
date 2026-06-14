using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public sealed record WooShippingRatesResponse(
    string CorrelationId,
    IReadOnlyList<WooShippingRateDto> Methods);