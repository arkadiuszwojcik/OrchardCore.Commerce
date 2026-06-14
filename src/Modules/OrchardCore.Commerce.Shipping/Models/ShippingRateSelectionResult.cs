namespace OrchardCore.Commerce.Shipping.Models;

public sealed record ShippingRateSelectionResult(
    bool Succeeded,
    string? Message = null);