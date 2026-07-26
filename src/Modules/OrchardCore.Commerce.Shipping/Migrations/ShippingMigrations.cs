using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.ContentManagement.Metadata;
using OrchardCore.ContentManagement.Metadata.Settings;
using OrchardCore.Data.Migration;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping;

public class ShippingMigrations : DataMigration
{
    private readonly IContentDefinitionManager _contentDefinitionManager;

    public ShippingMigrations(IContentDefinitionManager contentDefinitionManager)
    {
        _contentDefinitionManager = contentDefinitionManager;
    }

    public async Task<int> CreateAsync()
    {
        await _contentDefinitionManager.AlterPartDefinitionAsync<ShippingProviderConnectionPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Credentials and settings for a shipping carrier account.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("ShippingProviderConnection", type => type
            .DisplayedAs("Shipping Provider Connection")
            .Creatable()
            .Listable()
            .Draftable()
            .WithPart(nameof(ShippingProviderConnectionPart)));

        await _contentDefinitionManager.AlterPartDefinitionAsync<ShippingOriginPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Warehouse or fulfillment center address.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("ShippingOrigin", type => type
            .DisplayedAs("Shipping Origin")
            .Creatable()
            .Listable()
            .Draftable()
            .WithPart(nameof(ShippingOriginPart)));

        await _contentDefinitionManager.AlterPartDefinitionAsync<ShippingMethodPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Customer-facing shipping option.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("ShippingMethod", type => type
            .DisplayedAs("Shipping Method")
            .Creatable()
            .Listable()
            .Draftable()
            .WithPart(nameof(ShippingMethodPart)));

        await _contentDefinitionManager.AlterPartDefinitionAsync<ShipmentPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Physical shipment with tracking and fulfillment information.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("Shipment", type => type
            .DisplayedAs("Shipment")
            .Creatable()
            .Listable()
            .Draftable()
            .WithPart(nameof(ShipmentPart)));

        await _contentDefinitionManager.AlterPartDefinitionAsync<ShippingOrderPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Shipping quote snapshot for an order.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("Order", type => type
            .WithPart(nameof(ShippingOrderPart)));

        return 1;
    }
}
