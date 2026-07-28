using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Modules;

namespace OrchardCore.Commerce.Shipping.InPost;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        // Typed HttpClient for the ShipX REST API.
        services.AddHttpClient<InPostApiClient>();

        // Register InPost provider.
        services.AddTransient<IShippingProvider, InPostShippingProvider>();
        services.AddTransient<IShippingRateProvider, InPostShippingProvider>();
        services.AddTransient<IShippingServiceCatalogProvider, InPostShippingProvider>();
        services.AddTransient<IShipmentProvider, InPostShippingProvider>();
        services.AddTransient<IShippingTrackingProvider, InPostShippingProvider>();
        services.AddTransient<IPickupPointProvider, InPostShippingProvider>();
    }
}

