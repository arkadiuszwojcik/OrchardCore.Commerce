using Lombiq.HelpfulLibraries.OrchardCore.Navigation;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Shipping.Drivers;
using OrchardCore.Commerce.Shipping.Permissions;
using OrchardCore.Navigation;

namespace OrchardCore.Commerce.Shipping.Navigation;

public class ShippingAdminMenu : AdminMenuNavigationProviderBase
{
    public ShippingAdminMenu(IHttpContextAccessor hca, IStringLocalizer<ShippingAdminMenu> stringLocalizer)
        : base(hca, stringLocalizer)
    {
    }

    protected override void Build(NavigationBuilder builder) =>
        builder.AddCommerce(T, commerce => commerce
            .Add(T["Shipping"], T["Shipping"], shipping => shipping
                .Add(T["Provider Connections"], T["Provider Connections"], entry => entry
                    .Action("List", "Admin", new { area = "OrchardCore.Contents", contentTypeId = "ShippingProviderConnection" })
                    .Permission(ShippingPermissions.ManageShippingProviderConnections)
                    .LocalNav())
                .Add(T["Origins"], T["Origins"], entry => entry
                    .Action("List", "Admin", new { area = "OrchardCore.Contents", contentTypeId = "ShippingOrigin" })
                    .Permission(ShippingPermissions.ManageShippingOrigins)
                    .LocalNav())
                .Add(T["Methods"], T["Methods"], entry => entry
                    .Action("List", "Admin", new { area = "OrchardCore.Contents", contentTypeId = "ShippingMethod" })
                    .Permission(ShippingPermissions.ManageShippingMethods)
                    .LocalNav())
                .Add(T["Shipments"], T["Shipments"], entry => entry
                    .Action("List", "Admin", new { area = "OrchardCore.Contents", contentTypeId = "Shipment" })
                    .Permission(ShippingPermissions.ManageShipments)
                    .LocalNav())
                .Add(T["Settings"], T["Settings"], entry => entry
                    .SiteSettings(ShippingSettingsDisplayDriver.EditorGroupId)
                    .Permission(ShippingPermissions.ManageShippingSettings)
                    .LocalNav())));
}
