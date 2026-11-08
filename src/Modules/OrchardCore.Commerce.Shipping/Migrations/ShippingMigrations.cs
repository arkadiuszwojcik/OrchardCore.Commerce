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
        await _contentDefinitionManager.AlterPartDefinitionAsync<ShippingOriginPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Warehouse or fulfillment center address.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("ShippingOrigin", type => type
            .WithDisplayName("Shipping Origin")
            .Creatable()
            .Listable()
            .Draftable()
            .WithPart(nameof(ShippingOriginPart)));

        await _contentDefinitionManager.AlterPartDefinitionAsync<ShippingMethodPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Customer-facing shipping option.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("ShippingMethod", type => type
            .WithDisplayName("Shipping Method")
            .Creatable()
            .Listable()
            .Draftable()
            .WithPart(nameof(ShippingMethodPart)));

        await _contentDefinitionManager.AlterPartDefinitionAsync<ShipmentPart>(part => part
            .Configure(p => p
                .Attachable()
                .WithDescription("Physical shipment with tracking and fulfillment information.")));

        await _contentDefinitionManager.AlterTypeDefinitionAsync("Shipment", type => type
            .WithDisplayName("Shipment")
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

        return 2;
    }

    // Provider connections used to be content items of the "ShippingProviderConnection" type. They are plain
    // entities in a document now, so the content type and part definitions are no longer needed.
    public async Task<int> UpdateFrom1Async()
    {
        await _contentDefinitionManager.DeleteTypeDefinitionAsync("ShippingProviderConnection");
        await _contentDefinitionManager.DeletePartDefinitionAsync("ShippingProviderConnectionPart");

        return 2;
    }
}
