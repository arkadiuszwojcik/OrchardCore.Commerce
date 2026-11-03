using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Modules;
using System;

namespace OrchardCore.Commerce.Shipping.InPost;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        // Typed HttpClient for the ShipX REST API.
        services.AddHttpClient<InPostApiClient>(client => client.Timeout = TimeSpan.FromSeconds(30));

        // Register InPost provider once; capability interfaces alias the same scoped instance.
        services.AddScoped<InPostShippingProvider>();
        services.AddScoped<IShippingProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShippingRateProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShippingServiceCatalogProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShipmentProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShippingTrackingProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IPickupPointProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
    }
}

