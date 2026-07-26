using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.ContentManagement;
using OrchardCore.Modules;

namespace OrchardCore.Commerce.Shipping;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        services.AddContentPart<ShippingProviderConnectionPart>()
            .WithMigration<ShippingMigrations>();

        services.AddContentPart<ShippingOriginPart>();
        services.AddContentPart<ShippingMethodPart>();
        services.AddContentPart<ShippingOrderPart>();
        services.AddContentPart<ShipmentPart>();
    }
}
