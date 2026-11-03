#nullable enable
using Microsoft.Extensions.Localization;
using OrchardCore.Workflows.Abstractions.Models;
using OrchardCore.Workflows.Activities;
using OrchardCore.Workflows.Models;
using System.Collections.Generic;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Workflow task that purchases a shipment label via the configured provider.
/// Expects <c>ShipmentId</c> in workflow input; writes <c>TrackingNumber</c> to workflow output.
/// </summary>
public class PurchaseShipmentTask : TaskActivity<PurchaseShipmentTask>
{
    private readonly Services.IShipmentPurchaseService _purchaseService;
    protected readonly IStringLocalizer S;

    public PurchaseShipmentTask(
        Services.IShipmentPurchaseService purchaseService,
        IStringLocalizer<PurchaseShipmentTask> localizer)
    {
        _purchaseService = purchaseService;
        S = localizer;
    }

    public override LocalizedString DisplayText => S["Purchase Shipment"];
    public override LocalizedString Category => S["Shipping"];

    public override IEnumerable<Outcome> GetPossibleOutcomes(
        WorkflowExecutionContext workflowContext,
        ActivityContext activityContext) =>
        Outcomes(S["Done"], S["Error"]);

    public override async Task<ActivityExecutionResult> ExecuteAsync(
        WorkflowExecutionContext workflowContext,
        ActivityContext activityContext)
    {
        var shipmentContentItemId = workflowContext.Input.TryGetValue("ShipmentId", out var shipIdObj)
            ? shipIdObj as string
            : null;
        if (string.IsNullOrEmpty(shipmentContentItemId)) return Outcomes("Error");

        var result = await _purchaseService.PurchaseAsync(shipmentContentItemId);
        if (!result.IsSuccess) return Outcomes("Error");

        workflowContext.Output["TrackingNumber"] = result.TrackingNumber;
        workflowContext.Output["TrackingUrl"] = result.TrackingUrl;

        return Outcomes("Done");
    }
}
