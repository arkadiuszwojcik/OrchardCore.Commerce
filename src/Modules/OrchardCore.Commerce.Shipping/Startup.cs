using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Abstractions.Abstractions;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Constants;
using OrchardCore.Commerce.Shipping.Events;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.Modules;

namespace OrchardCore.Commerce.Shipping;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        services.AddHttpContextAccessor();
        services.AddScoped<IShippingService, ShippingService>();
        services.AddScoped<IShippingSelectionStore, SessionShippingSelectionStore>();
        services.AddScoped<IOrderShippingCostApplier, OrderShippingCostApplier>();
        services.AddScoped<ICheckoutEvents, ShippingCheckoutEvents>();
        services.AddScoped<IOrderEvents, ShippingOrderEvents>();
    }
}

[Feature(FeatureIds.FlatRateProvider)]
public class FlatRateProviderStartup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services) =>
        services.AddScoped<IShippingRateProvider, FlatRateShippingProvider>();
}