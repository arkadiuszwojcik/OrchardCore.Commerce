using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.ContentManagement.Display.Models;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShippingProviderConnectionPartDisplayDriver : ContentPartDisplayDriver<ShippingProviderConnectionPart>
{
    private readonly IShippingProviderRegistry _providerRegistry;
    private readonly IShippingCredentialsService _credentialsService;

    public ShippingProviderConnectionPartDisplayDriver(
        IShippingProviderRegistry providerRegistry,
        IShippingCredentialsService credentialsService)
    {
        _providerRegistry = providerRegistry;
        _credentialsService = credentialsService;
    }

    public override IDisplayResult Edit(ShippingProviderConnectionPart part, BuildPartEditorContext context) =>
        Initialize<ShippingProviderConnectionPartViewModel>(GetEditorShapeType(context), async vm =>
        {
            vm.ProviderKey = part.ProviderKey.Text ?? string.Empty;
            vm.ConnectionName = part.ConnectionName.Text ?? string.Empty;
            vm.CredentialsJson = string.Empty;
            vm.HasStoredCredentials = !string.IsNullOrEmpty(part.ContentItem.ContentItemId) &&
                await _credentialsService.HasCredentialsAsync(part.ContentItem.ContentItemId);
            vm.IsEnabled = part.IsEnabled.Value;
            vm.IsTestMode = part.IsTestMode.Value;
            vm.LastHealthStatus = part.LastHealthStatus.Text ?? string.Empty;
            vm.NormalizationProfileJson = part.NormalizationProfileJson.Text ?? string.Empty;
            vm.AvailableProviderKeys = _providerRegistry.GetAllProviders()
                .Select(p => p.ProviderKey)
                .ToList();
        });

    public override async Task<IDisplayResult> UpdateAsync(ShippingProviderConnectionPart part, UpdatePartEditorContext context)
    {
        var vm = await context.CreateModelAsync<ShippingProviderConnectionPartViewModel>(Prefix);

        var configChanged =
            part.ProviderKey.Text != vm.ProviderKey ||
            part.IsTestMode.Value != vm.IsTestMode ||
            part.NormalizationProfileJson.Text != vm.NormalizationProfileJson;

        part.ProviderKey.Text = vm.ProviderKey;
        part.ConnectionName.Text = vm.ConnectionName;
        // Credentials are write-only: a blank value keeps what is stored, a non-blank one replaces it.
        if (!string.IsNullOrWhiteSpace(vm.CredentialsJson))
        {
            Dictionary<string, string>? parsed = null;
            try
            {
                parsed = JsonSerializer.Deserialize<Dictionary<string, string>>(vm.CredentialsJson);
            }
            catch (JsonException)
            {
                // Reported below.
            }

            if (parsed is null)
            {
                context.Updater.ModelState.AddModelError(
                    Prefix + "." + nameof(vm.CredentialsJson),
                    "Credentials must be a JSON object of string values.");
            }
            else
            {
                await _credentialsService.SaveCredentialsAsync(part.ContentItem.ContentItemId, parsed);
                part.CredentialVersion.Value = (part.CredentialVersion.Value ?? 0) + 1;
                configChanged = true;
            }
        }
        part.IsEnabled.Value = vm.IsEnabled;
        part.IsTestMode.Value = vm.IsTestMode;
        part.NormalizationProfileJson.Text = vm.NormalizationProfileJson;

        if (configChanged)
        {
            part.ConfigurationRevision.Value = (part.ConfigurationRevision.Value ?? 0) + 1;
        }

        return await EditAsync(part, context);
    }
}
