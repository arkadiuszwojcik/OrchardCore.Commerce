namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Computes <see cref="ShippingProviderCapabilities"/> from the capability interfaces a provider implements.
/// </summary>
public static class ShippingProviderCapabilitiesExtensions
{
    public static ShippingProviderCapabilities Compute(IShippingProvider provider)
    {
        var result = ShippingProviderCapabilities.None;
        if (provider is IShippingRateProvider) result |= ShippingProviderCapabilities.RateQuotes;
        if (provider is IShipmentProvider) result |= ShippingProviderCapabilities.PurchaseLabels | ShippingProviderCapabilities.CancelShipment;
        if (provider is IShippingTrackingProvider) result |= ShippingProviderCapabilities.Tracking;
        if (provider is IPickupPointProvider) result |= ShippingProviderCapabilities.PickupPoints;
        return result;
    }
}
