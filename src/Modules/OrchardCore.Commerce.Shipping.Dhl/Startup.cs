using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Modules;

namespace OrchardCore.Commerce.Shipping.Dhl;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        // Register DHL provider
        services.AddTransient<IShippingProvider, DhlShippingProvider>();
        services.AddTransient<IShippingRateProvider, DhlShippingProvider>();
        services.AddTransient<IShipmentProvider, DhlShippingProvider>();
        services.AddTransient<IShippingTrackingProvider, DhlShippingProvider>();
    }
}
