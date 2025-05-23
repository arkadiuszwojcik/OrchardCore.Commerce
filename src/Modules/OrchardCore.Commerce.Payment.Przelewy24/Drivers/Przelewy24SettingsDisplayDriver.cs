using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Payment.Przelewy24.Models;
using OrchardCore.DisplayManagement.Entities;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using OrchardCore.Environment.Shell;
using OrchardCore.Settings;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Drivers;

public class Przelewy24SettingsDisplayDriver : SiteDisplayDriver<Przelewy24Settings>
{
    public const string EditorGroupId = "Przelewy24";

    private readonly IAuthorizationService _authorizationService;
    private readonly IHttpContextAccessor _hca;
    private readonly IShellReleaseManager _shellReleaseManager;
    private readonly Przelewy24Settings _ssoSettings;

    protected override string SettingsGroupId => EditorGroupId;

    public Przelewy24SettingsDisplayDriver(
        IAuthorizationService authorizationService,
        IHttpContextAccessor hca,
        IShellReleaseManager shellReleaseManager,
        IOptionsSnapshot<Przelewy24Settings> ssoSettings)
    {
        _authorizationService = authorizationService;
        _hca = hca;
        _shellReleaseManager = shellReleaseManager;
        _ssoSettings = ssoSettings.Value;
    }

    public override async Task<IDisplayResult> EditAsync(ISite model, Przelewy24Settings section, BuildEditorContext context)
    {
        if (!await AuthorizeAsync()) return null;

        context.AddTenantReloadWarningWrapper();

        return Initialize<Przelewy24Settings>($"{nameof(Przelewy24Settings)}_Edit", settings =>
        {
            _ssoSettings.CopyTo(settings);
            settings.ApiKey = string.Empty;
        })
            .PlaceInContent()
            .OnGroup(SettingsGroupId);
    }

    public override async Task<IDisplayResult> UpdateAsync(ISite model, Przelewy24Settings section, UpdateEditorContext context)
    {
        if (await context.CreateModelMaybeAsync<Przelewy24Settings>(Prefix, AuthorizeAsync) is not { } viewModel)
        {
            return null;
        }

        viewModel.CopyTo(section);

        // Release the tenant to apply settings.
        _shellReleaseManager.RequestRelease();

        return await EditAsync(model, section, context);
    }

    private Task<bool> AuthorizeAsync() =>
        _authorizationService.AuthorizeCurrentUserAsync(_hca.HttpContext, Permissions.ManagePrzelewy24Settings);
}
