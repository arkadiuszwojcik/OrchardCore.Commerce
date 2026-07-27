using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Permissions;
using OrchardCore.DisplayManagement.Entities;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using OrchardCore.Environment.Shell;
using OrchardCore.Settings;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShippingSettingsDisplayDriver : SiteDisplayDriver<ShippingOptions>
{
    public const string EditorGroupId = "ShippingSettings";

    private readonly IAuthorizationService _authorizationService;
    private readonly IHttpContextAccessor _hca;
    private readonly IShellReleaseManager _shellReleaseManager;
    private readonly ShippingOptions _currentSettings;

    protected override string SettingsGroupId => EditorGroupId;

    public ShippingSettingsDisplayDriver(
        IAuthorizationService authorizationService,
        IHttpContextAccessor hca,
        IShellReleaseManager shellReleaseManager,
        IOptionsSnapshot<ShippingOptions> currentSettings)
    {
        _authorizationService = authorizationService;
        _hca = hca;
        _shellReleaseManager = shellReleaseManager;
        _currentSettings = currentSettings.Value;
    }

    public override async Task<IDisplayResult> EditAsync(ISite model, ShippingOptions section, BuildEditorContext context)
    {
        if (!await AuthorizeAsync()) return null;

        return Initialize<ShippingOptions>($"{nameof(ShippingOptions)}_Edit", settings =>
            _currentSettings.CopyTo(settings))
            .PlaceInContent()
            .OnGroup(SettingsGroupId);
    }

    public override async Task<IDisplayResult> UpdateAsync(ISite model, ShippingOptions section, UpdateEditorContext context)
    {
        if (await context.CreateModelMaybeAsync<ShippingOptions>(Prefix, AuthorizeAsync) is not { } viewModel)
        {
            return null;
        }

        viewModel.CopyTo(section);

        _shellReleaseManager.RequestRelease();

        return await EditAsync(model, section, context);
    }

    private Task<bool> AuthorizeAsync() =>
        _authorizationService.AuthorizeCurrentUserAsync(_hca.HttpContext, ShippingPermissions.ManageShippingSettings);
}
