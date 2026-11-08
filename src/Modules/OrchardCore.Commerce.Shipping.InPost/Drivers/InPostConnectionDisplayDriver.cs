using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Commerce.Shipping.InPost.Models;
using OrchardCore.Commerce.Shipping.InPost.ViewModels;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using OrchardCore.Entities;
using OrchardCore.Mvc.ModelBinding;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Drivers;

/// <summary>
/// The connection editor of the InPost provider. It is shown only for InPost connections, next to the shared fields
/// of the core editor.
/// </summary>
public sealed class InPostConnectionDisplayDriver : DisplayDriver<ShippingProviderConnection>
{
    private readonly IShippingCredentialsService _credentialsService;
    private readonly IStringLocalizer S;

    public InPostConnectionDisplayDriver(
        IShippingCredentialsService credentialsService,
        IStringLocalizer<InPostConnectionDisplayDriver> stringLocalizer)
    {
        _credentialsService = credentialsService;
        S = stringLocalizer;
    }

    public override IDisplayResult? Edit(ShippingProviderConnection connection, BuildEditorContext context)
    {
        if (connection.ProviderKey != InPostShippingProvider.Key) return null;

        return Initialize<InPostConnectionViewModel>("InPostConnection_Edit", async model =>
        {
            model.OrganizationId = connection.GetOrCreate<InPostConnectionSettings>().OrganizationId ?? string.Empty;
            model.HasApiToken = await HasApiTokenAsync(connection);
            model.ConnectionId = connection.Id;
            model.IsTestMode = connection.IsTestMode;
            model.SandboxBaseUrl = InPostConnection.SandboxBaseUrl;
            model.ProductionBaseUrl = InPostConnection.ProductionBaseUrl;
        }).Location("Content:5");
    }

    public override async Task<IDisplayResult?> UpdateAsync(ShippingProviderConnection connection, UpdateEditorContext context)
    {
        if (connection.ProviderKey != InPostShippingProvider.Key) return null;

        var model = new InPostConnectionViewModel();
        await context.Updater.TryUpdateModelAsync(model, Prefix, m => m.OrganizationId, m => m.ApiToken);

        var organizationId = model.OrganizationId?.Trim();
        if (string.IsNullOrEmpty(organizationId))
        {
            context.Updater.ModelState.AddModelError(Prefix, nameof(model.OrganizationId), S["The organization ID is required."]);
        }

        if (string.IsNullOrWhiteSpace(model.ApiToken) && !await HasApiTokenAsync(connection))
        {
            context.Updater.ModelState.AddModelError(Prefix, nameof(model.ApiToken), S["The API token is required."]);
        }

        var settings = connection.GetOrCreate<InPostConnectionSettings>();
        if (settings.OrganizationId != organizationId)
        {
            settings.OrganizationId = organizationId;
            connection.Put(settings);
            connection.MarkConfigurationChanged();
        }

        // The token is write-only: a blank value keeps the stored one. The new one is stored when the connection is saved.
        if (!string.IsNullOrWhiteSpace(model.ApiToken))
        {
            connection.SetCredential(InPostConnection.ApiTokenKey, model.ApiToken.Trim());
        }

        return Edit(connection, context);
    }

    private async Task<bool> HasApiTokenAsync(ShippingProviderConnection connection) =>
        (await _credentialsService.GetCredentialsAsync(connection.Id)).ContainsKey(InPostConnection.ApiTokenKey);
}
