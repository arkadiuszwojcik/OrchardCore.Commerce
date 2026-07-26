using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.ContentManagement.Display.Models;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShipmentPartDisplayDriver : ContentPartDisplayDriver<ShipmentPart>
{
    public override IDisplayResult Display(ShipmentPart part, BuildPartDisplayContext context) =>
        Initialize<ShipmentPartViewModel>(GetDisplayShapeType(context), vm => PopulateViewModel(vm, part))
            .Location("Detail", "Content:5")
            .Location("Summary", "Meta");

    public override IDisplayResult Edit(ShipmentPart part, BuildPartEditorContext context) =>
        Initialize<ShipmentPartViewModel>(GetEditorShapeType(context), vm => PopulateViewModel(vm, part));

    public override async Task<IDisplayResult> UpdateAsync(ShipmentPart part, UpdatePartEditorContext context)
    {
        var vm = await context.CreateModelAsync<ShipmentPartViewModel>(Prefix);

        // Only the editable field is FulfillmentStatus — others are system-set
        part.FulfillmentStatus.Text = vm.FulfillmentStatus;

        return await EditAsync(part, context);
    }

    private static void PopulateViewModel(ShipmentPartViewModel vm, ShipmentPart part)
    {
        vm.ShipmentId = part.ShipmentId.Text ?? string.Empty;
        vm.OrderId = part.OrderId.Text ?? string.Empty;
        vm.TrackingNumber = part.TrackingNumber.Text ?? string.Empty;
        vm.TrackingUrl = part.TrackingUrl.Text ?? string.Empty;
        vm.PurchaseStatus = part.PurchaseStatus.Text ?? "NotPurchased";
        vm.FulfillmentStatus = part.FulfillmentStatus.Text ?? "Pending";
        vm.TrackingStatus = part.TrackingStatus.Text ?? "Unknown";
        vm.HasLabel = !string.IsNullOrEmpty(part.LabelDocumentId.Text);
        vm.LabelDocumentId = part.LabelDocumentId.Text ?? string.Empty;
    }
}
