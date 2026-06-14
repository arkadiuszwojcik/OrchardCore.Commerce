using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.InPost.Options;
using OrchardCore.Commerce.Shipping.InPost.Permissions;
using OrchardCore.DisplayManagement.Entities;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using OrchardCore.Settings;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Drivers;

public class InPostShippingOptionsDisplayDriver : SiteDisplayDriver<InPostShippingOptions>
{
    public const string GroupId = nameof(InPostShippingOptions);

    private readonly IHttpContextAccessor _hca;
    private readonly IAuthorizationService _authorizationService;
    private readonly InPostShippingOptions _currentOptions;

    protected override string SettingsGroupId => GroupId;

    public InPostShippingOptionsDisplayDriver(
        IHttpContextAccessor hca,
        IAuthorizationService authorizationService,
        IOptionsSnapshot<InPostShippingOptions> currentOptions)
    {
        _hca = hca;
        _authorizationService = authorizationService;
        _currentOptions = currentOptions.Value;
    }

    public override async Task<IDisplayResult?> EditAsync(ISite model, InPostShippingOptions section, BuildEditorContext context)
    {
        if (!await AuthorizeAsync()) return null;

        context.AddTenantReloadWarningWrapper();

        return Initialize<InPostShippingOptions>(
                "InPostShippingOptions_Edit",
                settings =>
                {
                    _currentOptions.CopyTo(settings);
                    settings.ApiToken = string.Empty;
                })
            .PlaceInContent()
            .OnGroup(SettingsGroupId);
    }

    public override async Task<IDisplayResult?> UpdateAsync(ISite model, InPostShippingOptions section, UpdateEditorContext context)
    {
        if (!await AuthorizeAsync()) return null;

        if (await context.CreateModelMaybeAsync<InPostShippingOptions>(Prefix, AuthorizeAsync) is { } viewModel)
        {
            if (string.IsNullOrWhiteSpace(viewModel.ApiToken))
            {
                viewModel.ApiToken = _currentOptions.ApiToken;
            }

            viewModel.CopyTo(section);
        }

        return await EditAsync(model, section, context);
    }

    private Task<bool> AuthorizeAsync() =>
        _authorizationService.AuthorizeAsync(_hca.HttpContext?.User, InPostShippingPermissions.ManageInPostShippingSettings);
}