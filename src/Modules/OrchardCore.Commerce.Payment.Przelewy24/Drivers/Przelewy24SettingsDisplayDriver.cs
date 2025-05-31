using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.DataProtection;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Payment.Przelewy24.Constants;
using OrchardCore.Commerce.Payment.Przelewy24.Extensions;
using OrchardCore.Commerce.Payment.Przelewy24.Settings;
using OrchardCore.Commerce.Payment.Przelewy24.ViewModels;
using OrchardCore.DisplayManagement.Entities;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using OrchardCore.Environment.Shell;
using OrchardCore.Settings;
using System.Security.Cryptography;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Drivers;

public class Przelewy24SettingsDisplayDriver : SiteDisplayDriver<Przelewy24Settings>
{
    public const string EditorGroupId = "Przelewy24";

    private readonly IAuthorizationService _authorizationService;
    private readonly IHttpContextAccessor _httpContextAccessor;
    private readonly IShellReleaseManager _shellReleaseManager;
    private readonly IDataProtectionProvider _dataProtectionProvider;
    private readonly ILogger _logger;

    protected override string SettingsGroupId => EditorGroupId;

    public Przelewy24SettingsDisplayDriver(
        IAuthorizationService authorizationService,
        IHttpContextAccessor httpContextAccessor,
        IShellReleaseManager shellReleaseManager,
        IDataProtectionProvider dataProtectionProvider,
        ILogger<Przelewy24SettingsDisplayDriver> logger)
    {
        _authorizationService = authorizationService;
        _httpContextAccessor = httpContextAccessor;
        _shellReleaseManager = shellReleaseManager;
        _dataProtectionProvider = dataProtectionProvider;
        _logger = logger;
    }

    public override async Task<IDisplayResult> EditAsync(ISite model, Przelewy24Settings settings, BuildEditorContext context)
    {
        if (!await AuthorizeAsync()) return null;

        context.AddTenantReloadWarningWrapper();

        return Initialize<Przelewy24SettingsViewModel>($"{nameof(Przelewy24Settings)}_Edit", model =>
        {
            var protector = _dataProtectionProvider.CreateProtector(Przelewy24Constants.Features.Przelewy24);

            model.MerchantId = settings.MerchantId;
            model.PosId = settings.PosId;

            if (!string.IsNullOrWhiteSpace(settings.ApiKey))
            {
                try
                {
                    model.ApiKey = protector.Unprotect(settings.ApiKey);
                }
                catch (CryptographicException)
                {
                    _logger.LogError("The API key could not be decrypted. It may have been encrypted using a different key.");
                    model.ApiKey = string.Empty;
                    model.HasDecryptionError = true;
                }
            }
            else
            {
                model.ApiKey = string.Empty;
            }

            if (!string.IsNullOrWhiteSpace(settings.CrcKey))
            {
                try
                {
                    model.CrcKey = protector.Unprotect(settings.CrcKey);
                }
                catch (CryptographicException)
                {
                    _logger.LogError("The CRC key could not be decrypted. It may have been encrypted using a different key.");
                    model.CrcKey = string.Empty;
                    model.HasDecryptionError = true;
                }
            }
            else
            {
                model.CrcKey = string.Empty;
            }
        })
            .PlaceInContent()
            .OnGroup(SettingsGroupId);
    }

    // TIP: If ModelState have no error then settings object will be saved to database automatically after this call
    public override async Task<IDisplayResult> UpdateAsync(ISite site, Przelewy24Settings settings, UpdateEditorContext context)
    {
        if (await context.CreateModelMaybeAsync<Przelewy24SettingsViewModel>(Prefix, AuthorizeAsync) is not { } viewModel)
        {
            return null;
        }

        if (context.Updater.ModelState.IsValid)
        {
            settings.MerchantId = viewModel.MerchantId;
            settings.PosId = viewModel.PosId;
            settings.ApiKey = viewModel.ApiKey.EncryptSecretString(_dataProtectionProvider);
            settings.CrcKey = viewModel.CrcKey.EncryptSecretString(_dataProtectionProvider);
        }

        // Release the tenant to apply settings.
        _shellReleaseManager.RequestRelease();

        return await EditAsync(site, settings, context);
    }

    private Task<bool> AuthorizeAsync() =>
        _authorizationService.AuthorizeCurrentUserAsync(_httpContextAccessor.HttpContext, Permissions.ManagePrzelewy24Settings);
}
