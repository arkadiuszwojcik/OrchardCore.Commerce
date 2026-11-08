using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Routing;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.DependencyInjection.Extensions;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Activities;
using OrchardCore.Commerce.Shipping.Drivers;
using OrchardCore.Commerce.Shipping.Endpoints.Extensions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Navigation;
using OrchardCore.Commerce.Shipping.Permissions;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.ContentManagement;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.Data;
using OrchardCore.Data.Migration;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.Modules;
using OrchardCore.Navigation;
using OrchardCore.Security.Permissions;
using OrchardCore.Workflows.Helpers;
using System;

namespace OrchardCore.Commerce.Shipping;

public class Startup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        // Provider registry — collects all IShippingProvider registrations from any module
        services.AddScoped<IShippingProviderRegistry, ShippingProviderRegistry>();
        services.TryAddSingleton(TimeProvider.System);

        // Placeholder plain-text secrets until the Orchard Core Secrets module is available.
        services.TryAddEnumerable(ServiceDescriptor.Singleton<OrchardCore.Secrets.ISecretStore, Services.Secrets.PlainTextFileSecretStore>());
        services.TryAddSingleton<OrchardCore.Secrets.ISecretManager, Services.Secrets.SecretManager>();
        services.AddScoped<IShippingCredentialsService, ShippingCredentialsService>();

        // Provider connections are plain entities stored in one document, not content items. The core driver edits the
        // shared fields; every provider module adds its own driver for the fields specific to it.
        services.AddDataMigration<ShippingMigrations>();
        services.AddScoped<IShippingProviderConnectionStore, ShippingProviderConnectionStore>();
        services.AddScoped<IShippingProviderConnectionManager, ShippingProviderConnectionManager>();
        services.AddDisplayDriver<ShippingProviderConnection, ShippingProviderConnectionsDisplayDriver>();

        // Content parts + display drivers
        services.AddContentPart<ShippingOriginPart>()
            .UseDisplayDriver<ShippingOriginPartDisplayDriver>();

        services.AddContentPart<ShippingMethodPart>()
            .UseDisplayDriver<ShippingMethodPartDisplayDriver>();

        services.AddContentPart<ShippingOrderPart>()
            .UseDisplayDriver<ShippingOrderPartDisplayDriver>();

        services.AddContentPart<ShipmentPart>()
            .UseDisplayDriver<ShipmentPartDisplayDriver>();

        // Settings
        services.AddTransient<IConfigureOptions<ShippingOptions>, ShippingSettingsConfiguration>();
        services.AddSiteDisplayDriver<ShippingSettingsDisplayDriver>();

        // Core backend services
        services.AddScoped<IShippingRateService, ShippingRateService>();
        services.AddScoped<IShipmentPurchaseService, ShipmentPurchaseService>();
        services.AddScoped<IShippingQuoteStore, ShippingQuoteStore>();
        services.AddScoped<IShippingPackerService, ShippingPackerService>();
        services.AddScoped<IShippingPhysicalDataResolver, ShippingPhysicalDataResolver>();
        services.AddScoped<IShipmentStateTransitionValidator, ShipmentStateTransitionValidator>();
        services.AddSingleton<IShippingRateCache, ShippingRateCache>();

        // Permissions & admin navigation
        services.AddScoped<IPermissionProvider, ShippingPermissions>();
        services.AddScoped<INavigationProvider, ShippingAdminMenu>();
    }
}

[Feature("OrchardCore.Commerce.Shipping.Api")]
[RequireFeatures("OrchardCore.Commerce.Shipping")]
public class ApiStartup : StartupBase
{
    public override void Configure(IApplicationBuilder app, IEndpointRouteBuilder routes, IServiceProvider serviceProvider) =>
        routes.AddShippingApiEndpoints();
}

[Feature("OrchardCore.Commerce.Shipping.Workflows")]
[RequireFeatures("OrchardCore.Commerce.Shipping")]
public class WorkflowsStartup : StartupBase
{
    public override void ConfigureServices(IServiceCollection services)
    {
        // Events
        services.AddActivity<ShippingQuoteSelectedEvent, ShippingQuoteSelectedEventDisplayDriver>();
        services.AddActivity<ShipmentCreatedEvent, ShipmentCreatedEventDisplayDriver>();
        services.AddActivity<ShipmentPurchaseCompletedEvent, ShipmentPurchaseCompletedEventDisplayDriver>();
        services.AddActivity<ShipmentPurchaseFailedEvent, ShipmentPurchaseFailedEventDisplayDriver>();
        services.AddActivity<ShipmentCancelledEvent, ShipmentCancelledEventDisplayDriver>();
        services.AddActivity<ShipmentTrackingUpdatedEvent, ShipmentTrackingUpdatedEventDisplayDriver>();

        // Tasks
        services.AddActivity<CreateDraftShipmentTask, CreateDraftShipmentTaskDisplayDriver>();
        services.AddActivity<PurchaseShipmentTask, PurchaseShipmentTaskDisplayDriver>();
        services.AddActivity<CancelShipmentTask, CancelShipmentTaskDisplayDriver>();
    }
}
