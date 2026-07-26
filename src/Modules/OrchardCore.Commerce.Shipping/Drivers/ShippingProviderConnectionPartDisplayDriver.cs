using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.ContentManagement.Display.Models;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using System.Linq;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShippingProviderConnectionPartDisplayDriver : ContentPartDisplayDriver<ShippingProviderConnectionPart>
{
    private readonly IShippingProviderRegistry _providerRegistry;

    public ShippingProviderConnectionPartDisplayDriver(IShippingProviderRegistry providerRegistry) =>
        _providerRegistry = providerRegistry;

    public override IDisplayResult Edit(ShippingProviderConnectionPart part, BuildPartEditorContext context) =>
        Initialize<ShippingProviderConnectionPartViewModel>(GetEditorShapeType(context), vm =>
        {
            vm.ProviderId = part.ProviderId.Text ?? string.Empty;
            vm.ConnectionName = part.ConnectionName.Text ?? string.Empty;
            vm.CredentialsJson = part.CredentialsJson.Text ?? string.Empty;
            vm.IsEnabled = part.IsEnabled.Value;
            vm.IsTestMode = part.IsTestMode.Value;
            vm.LastHealthStatus = part.LastHealthStatus.Text ?? string.Empty;
            vm.NormalizationProfileJson = part.NormalizationProfileJson.Text ?? string.Empty;
            vm.AvailableProviderIds = _providerRegistry.GetAllProviders()
                .Select(p => p.ProviderId)
                .ToList();
        });

    public override async Task<IDisplayResult> UpdateAsync(ShippingProviderConnectionPart part, UpdatePartEditorContext context)
    {
        var vm = await context.CreateModelAsync<ShippingProviderConnectionPartViewModel>(Prefix);

        part.ProviderId.Text = vm.ProviderId;
        part.ConnectionName.Text = vm.ConnectionName;
        part.CredentialsJson.Text = vm.CredentialsJson;
        part.IsEnabled.Value = vm.IsEnabled;
        part.IsTestMode.Value = vm.IsTestMode;
        part.NormalizationProfileJson.Text = vm.NormalizationProfileJson;

        return await EditAsync(part, context);
    }
}
