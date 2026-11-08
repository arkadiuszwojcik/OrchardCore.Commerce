using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Shipping.Dhl.Models;
using OrchardCore.Commerce.Shipping.Dhl.ViewModels;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using OrchardCore.Entities;
using OrchardCore.Mvc.ModelBinding;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Dhl.Drivers;

/// <summary>
/// The connection editor of the DHL provider. It is shown only for DHL connections, next to the shared fields of the
/// core editor.
/// </summary>
public sealed class DhlConnectionDisplayDriver : DisplayDriver<ShippingProviderConnection>
{
    public const string ApiKeyKey = "ApiKey";
    public const string ApiSecretKey = "ApiSecret";

    private readonly IShippingCredentialsService _credentialsService;
    private readonly IStringLocalizer S;

    public DhlConnectionDisplayDriver(
        IShippingCredentialsService credentialsService,
        IStringLocalizer<DhlConnectionDisplayDriver> stringLocalizer)
    {
        _credentialsService = credentialsService;
        S = stringLocalizer;
    }

    public override IDisplayResult Edit(ShippingProviderConnection connection, BuildEditorContext context)
    {
        if (connection.ProviderKey != DhlShippingProvider.Key) return null;

        return Initialize<DhlConnectionViewModel>("DhlConnection_Edit", async model =>
        {
            var credentials = await _credentialsService.GetCredentialsAsync(connection.Id);

            model.AccountNumber = connection.GetOrCreate<DhlConnectionSettings>().AccountNumber ?? string.Empty;
            model.HasApiKey = credentials.ContainsKey(ApiKeyKey);
            model.HasApiSecret = credentials.ContainsKey(ApiSecretKey);
        }).Location("Content:5");
    }

    public override async Task<IDisplayResult> UpdateAsync(ShippingProviderConnection connection, UpdateEditorContext context)
    {
        if (connection.ProviderKey != DhlShippingProvider.Key) return null;

        var model = new DhlConnectionViewModel();
        await context.Updater.TryUpdateModelAsync(model, Prefix, m => m.AccountNumber, m => m.ApiKey, m => m.ApiSecret);

        var credentials = await _credentialsService.GetCredentialsAsync(connection.Id);

        var accountNumber = model.AccountNumber?.Trim();
        if (string.IsNullOrEmpty(accountNumber))
        {
            context.Updater.ModelState.AddModelError(Prefix, nameof(model.AccountNumber), S["The account number is required."]);
        }

        if (string.IsNullOrWhiteSpace(model.ApiKey) && !credentials.ContainsKey(ApiKeyKey))
        {
            context.Updater.ModelState.AddModelError(Prefix, nameof(model.ApiKey), S["The API key is required."]);
        }

        if (string.IsNullOrWhiteSpace(model.ApiSecret) && !credentials.ContainsKey(ApiSecretKey))
        {
            context.Updater.ModelState.AddModelError(Prefix, nameof(model.ApiSecret), S["The API secret is required."]);
        }

        var settings = connection.GetOrCreate<DhlConnectionSettings>();
        if (settings.AccountNumber != accountNumber)
        {
            settings.AccountNumber = accountNumber;
            connection.Put(settings);
            connection.MarkConfigurationChanged();
        }

        // The key and secret are write-only: a blank value keeps the stored one. New ones are stored on save.
        if (!string.IsNullOrWhiteSpace(model.ApiKey)) connection.SetCredential(ApiKeyKey, model.ApiKey.Trim());
        if (!string.IsNullOrWhiteSpace(model.ApiSecret)) connection.SetCredential(ApiSecretKey, model.ApiSecret.Trim());

        return Edit(connection, context);
    }
}
