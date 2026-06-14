using Lombiq.HelpfulLibraries.OrchardCore.Navigation;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Shipping.InPost.Drivers;
using OrchardCore.Commerce.Shipping.InPost.Permissions;
using OrchardCore.Navigation;

namespace OrchardCore.Commerce.Shipping.InPost.Navigation;

public class AdminMenu : AdminMenuNavigationProviderBase
{
    public AdminMenu(IHttpContextAccessor hca, IStringLocalizer<AdminMenu> stringLocalizer)
        : base(hca, stringLocalizer)
    {
    }

    protected override void Build(NavigationBuilder builder) =>
        builder.AddCommerce(T, commerce => commerce
            .Add(T["Shipping - InPost"], T["Shipping - InPost"], entry => entry
                .SiteSettings(InPostShippingOptionsDisplayDriver.GroupId)
                .Permission(InPostShippingPermissions.ManageInPostShippingSettings)
                .LocalNav()));
}