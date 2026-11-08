using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Drivers;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.Modules;

namespace OrchardCore.Commerce.Shipping.InPost;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        // Refit-based client for the ShipX REST API.
        services.AddInPostApiClient();

        // Register InPost provider once; capability interfaces alias the same scoped instance.
        services.AddScoped<InPostShippingProvider>();
        services.AddScoped<IShippingProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShippingRateProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShippingServiceCatalogProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShipmentProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IShippingTrackingProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());
        services.AddScoped<IPickupPointProvider>(sp => sp.GetRequiredService<InPostShippingProvider>());

        // The connection editor for InPost connections. The Shipping module shows it next to the shared fields.
        services.AddDisplayDriver<ShippingProviderConnection, InPostConnectionDisplayDriver>();
    }
}
