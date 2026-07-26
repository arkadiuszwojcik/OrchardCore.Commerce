using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Drivers;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Navigation;
using OrchardCore.Commerce.Shipping.Permissions;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.ContentManagement;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.Modules;
using OrchardCore.Navigation;
using OrchardCore.Security.Permissions;

namespace OrchardCore.Commerce.Shipping;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        // Provider registry — collects all IShippingProvider registrations from any module
        services.AddSingleton<IShippingProviderRegistry, ShippingProviderRegistry>();

        // Content parts + migrations + display drivers
        services.AddContentPart<ShippingProviderConnectionPart>()
            .UseDisplayDriver<ShippingProviderConnectionPartDisplayDriver>()
            .WithMigration<ShippingMigrations>();

        services.AddContentPart<ShippingOriginPart>()
            .UseDisplayDriver<ShippingOriginPartDisplayDriver>();

        services.AddContentPart<ShippingMethodPart>()
            .UseDisplayDriver<ShippingMethodPartDisplayDriver>();

        services.AddContentPart<ShippingOrderPart>()
            .UseDisplayDriver<ShippingOrderPartDisplayDriver>();

        services.AddContentPart<ShipmentPart>()
            .UseDisplayDriver<ShipmentPartDisplayDriver>();

        // Permissions & admin navigation
        services.AddScoped<IPermissionProvider, ShippingPermissions>();
        services.AddScoped<INavigationProvider, ShippingAdminMenu>();
    }
}
