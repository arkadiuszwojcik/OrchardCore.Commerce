namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public sealed record WooShippingRateDto(
    string MethodId,
    string MethodTitle,
    decimal Cost,
    string Currency,
    string Provider,
    string InternalMethodCode);