using Lombiq.HelpfulLibraries.OrchardCore.Navigation;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Payment.Przelewy24.Drivers;
using OrchardCore.Navigation;

namespace OrchardCore.Commerce.Payment.Przelewy24;

public class AdminMenu : AdminMenuNavigationProviderBase
{
    public AdminMenu(IHttpContextAccessor hca, IStringLocalizer<AdminMenu> stringLocalizer)
        : base(hca, stringLocalizer)
    { }

    protected override void Build(NavigationBuilder builder) =>
        builder
            .Add(T["Configuration"], configuration => configuration
                .Add(T["Commerce"], commerce => commerce
                    .Add(T["Przelewy24 API"], T["Przelewy24 API"], entry => entry
                        .SiteSettings(Przelewy24SettingsDisplayDriver.EditorGroupId)
                        .Permission(Permissions.ManagePrzelewy24Settings)
                        .LocalNav())
                ));
}
