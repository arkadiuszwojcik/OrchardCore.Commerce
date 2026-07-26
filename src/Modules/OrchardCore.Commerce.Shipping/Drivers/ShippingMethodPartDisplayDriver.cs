using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.ContentManagement.Display.Models;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShippingMethodPartDisplayDriver : ContentPartDisplayDriver<ShippingMethodPart>
{
    public override IDisplayResult Edit(ShippingMethodPart part, BuildPartEditorContext context) =>
        Initialize<ShippingMethodPartViewModel>(GetEditorShapeType(context), vm =>
        {
            vm.DisplayName = part.DisplayName.Text ?? string.Empty;
            vm.Description = part.Description.Text ?? string.Empty;
            vm.ProviderConnectionId = part.ProviderConnectionId.Text ?? string.Empty;
            vm.ServiceId = part.ServiceId.Text ?? string.Empty;
            vm.OriginId = part.OriginId.Text ?? string.Empty;
            vm.IsEnabled = part.IsEnabled.Value;
            vm.SortOrder = (int)(part.SortOrder.Value ?? 0);
            vm.IconUrl = part.IconUrl.Text ?? string.Empty;
        });

    public override async Task<IDisplayResult> UpdateAsync(ShippingMethodPart part, UpdatePartEditorContext context)
    {
        var vm = await context.CreateModelAsync<ShippingMethodPartViewModel>(Prefix);

        part.DisplayName.Text = vm.DisplayName;
        part.Description.Text = vm.Description;
        part.ProviderConnectionId.Text = vm.ProviderConnectionId;
        part.ServiceId.Text = vm.ServiceId;
        part.OriginId.Text = vm.OriginId;
        part.IsEnabled.Value = vm.IsEnabled;
        part.SortOrder.Value = vm.SortOrder;
        part.IconUrl.Text = vm.IconUrl;

        return await EditAsync(part, context);
    }
}
