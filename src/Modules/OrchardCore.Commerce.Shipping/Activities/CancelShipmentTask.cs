#nullable enable
using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.ContentManagement;
using OrchardCore.Workflows.Abstractions.Models;
using OrchardCore.Workflows.Activities;
using OrchardCore.Workflows.Models;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Activities;

/// <summary>
/// Workflow task that cancels a purchased shipment via the provider.
/// Expects <c>ShipmentId</c> in workflow input; writes <c>Cancelled</c> (bool) to workflow output.
/// </summary>
public class CancelShipmentTask : TaskActivity<CancelShipmentTask>
{
    private readonly IContentManager _contentManager;
    private readonly Services.IShippingProviderConnectionManager _connectionManager;
    private readonly IShippingProviderRegistry _providerRegistry;
    protected readonly IStringLocalizer S;

    public CancelShipmentTask(
        IContentManager contentManager,
        Services.IShippingProviderConnectionManager connectionManager,
        IShippingProviderRegistry providerRegistry,
        IStringLocalizer<CancelShipmentTask> localizer)
    {
        _contentManager = contentManager;
        _connectionManager = connectionManager;
        _providerRegistry = providerRegistry;
        S = localizer;
    }

    public override LocalizedString DisplayText => S["Cancel Shipment"];
    public override LocalizedString Category => S["Shipping"];

    public override IEnumerable<Outcome> GetPossibleOutcomes(
        WorkflowExecutionContext workflowContext,
        ActivityContext activityContext) =>
        Outcomes(S["Done"], S["Failed"]);

    public override async Task<ActivityExecutionResult> ExecuteAsync(
        WorkflowExecutionContext workflowContext,
        ActivityContext activityContext)
    {
        var shipmentContentItemId = workflowContext.Input.TryGetValue("ShipmentId", out var shipIdObj)
            ? shipIdObj as string
            : null;
        if (string.IsNullOrEmpty(shipmentContentItemId)) return Outcomes("Failed");

        var shipmentItem = await _contentManager.GetAsync(shipmentContentItemId);
        if (shipmentItem is null) return Outcomes("Failed");

        if (!shipmentItem.TryGet<ShipmentPart>(out var shipmentPart)) return Outcomes("Failed");

        var connection = await _connectionManager.FindByIdAsync(shipmentPart.ProviderConnectionId.Text);
        if (connection is null) return Outcomes("Failed");

        if (_providerRegistry.GetProvider(connection.ProviderKey) is not IShipmentProvider shipmentProvider)
            return Outcomes("Failed");

        var context = await _connectionManager.CreateContextAsync(connection);
        var cancelled = await shipmentProvider.CancelShipmentAsync(context, shipmentPart.ShipmentId.Text ?? string.Empty);

        if (cancelled)
        {
            shipmentPart.FulfillmentStatus = new OrchardCore.ContentFields.Fields.TextField
            {
                Text = ShipmentFulfillmentStatus.Cancelled.ToString(),
            };
            shipmentItem.Apply(shipmentPart);
            await _contentManager.UpdateAsync(shipmentItem);
            await _contentManager.PublishAsync(shipmentItem);
        }

        workflowContext.Output["Cancelled"] = cancelled;

        return Outcomes(cancelled ? "Done" : "Failed");
    }

    private static T? TryDeserialize<T>(string? json) where T : class
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try { return JsonSerializer.Deserialize<T>(json); }
        catch { return null; }
    }
}
