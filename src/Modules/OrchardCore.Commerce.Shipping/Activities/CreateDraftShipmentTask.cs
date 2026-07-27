#nullable enable
using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.ContentManagement;
using OrchardCore.Workflows.Abstractions.Models;
using OrchardCore.Workflows.Activities;
using OrchardCore.Workflows.Models;
using System.Collections.Generic;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Workflow task that creates a draft shipment content item for an order.
/// Expects <c>OrderId</c> in workflow input; writes <c>ShipmentId</c> to workflow output.
/// </summary>
public class CreateDraftShipmentTask : TaskActivity<CreateDraftShipmentTask>
{
    private readonly IContentManager _contentManager;
    protected readonly IStringLocalizer S;

    public CreateDraftShipmentTask(
        IContentManager contentManager,
        IStringLocalizer<CreateDraftShipmentTask> localizer)
    {
        _contentManager = contentManager;
        S = localizer;
    }

    public override LocalizedString DisplayText => S["Create Draft Shipment"];
    public override LocalizedString Category => S["Shipping"];

    public override IEnumerable<Outcome> GetPossibleOutcomes(
        WorkflowExecutionContext workflowContext,
        ActivityContext activityContext) =>
        Outcomes(S["Done"], S["Error"]);

    public override async Task<ActivityExecutionResult> ExecuteAsync(
        WorkflowExecutionContext workflowContext,
        ActivityContext activityContext)
    {
        var orderId = workflowContext.Input.TryGetValue("OrderId", out var orderIdObj)
            ? orderIdObj as string
            : null;
        if (string.IsNullOrEmpty(orderId)) return Outcomes("Error");

        var shipmentItem = await _contentManager.NewAsync("Shipment");
        if (!shipmentItem.TryGet<ShipmentPart>(out var shipmentPart)) shipmentPart = new ShipmentPart();
        shipmentPart.OrderId = new OrchardCore.ContentFields.Fields.TextField { Text = orderId };
        shipmentItem.Apply(shipmentPart);

        await _contentManager.CreateAsync(shipmentItem);

        workflowContext.Output["ShipmentId"] = shipmentItem.ContentItemId;

        return Outcomes("Done");
    }
}
