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
/// Workflow task that purchases a shipment label via the configured provider.
/// Expects <c>ShipmentId</c> in workflow input; writes <c>TrackingNumber</c> to workflow output.
/// </summary>
public class PurchaseShipmentTask : TaskActivity<PurchaseShipmentTask>
{
    private readonly IContentManager _contentManager;
    private readonly IShippingProviderRegistry _providerRegistry;
    private readonly IShippingDocumentStore _documentStore;
    protected readonly IStringLocalizer S;

    public PurchaseShipmentTask(
        IContentManager contentManager,
        IShippingProviderRegistry providerRegistry,
        IShippingDocumentStore documentStore,
        IStringLocalizer<PurchaseShipmentTask> localizer)
    {
        _contentManager = contentManager;
        _providerRegistry = providerRegistry;
        _documentStore = documentStore;
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

        var shipmentItem = await _contentManager.GetAsync(shipmentContentItemId);
        if (shipmentItem is null) return Outcomes("Error");

        if (!shipmentItem.TryGet<ShipmentPart>(out var shipmentPart)) return Outcomes("Error");

        // Load connection to find provider.
        var connectionItem = await _contentManager.GetAsync(shipmentPart.ProviderConnectionId.Text);
        if (connectionItem is null) return Outcomes("Error");

        if (!connectionItem.TryGet<ShippingProviderConnectionPart>(out var connectionPart)) return Outcomes("Error");

        if (_providerRegistry.GetProvider(connectionPart.ProviderId.Text) is not IShipmentProvider shipmentProvider)
            return Outcomes("Error");

        // Deserialize stored addresses and packages.
        var origin = Deserialize<AddressSnapshot>(shipmentPart.OriginAddressJson.Text);
        var destination = Deserialize<AddressSnapshot>(shipmentPart.DestinationAddressJson.Text);
        var packages = Deserialize<System.Collections.Generic.List<ShippingPackage>>(shipmentPart.PackagesJson.Text)
            as IReadOnlyList<ShippingPackage> ?? System.Array.Empty<ShippingPackage>();

        if (origin is null || destination is null) return Outcomes("Error");

        var credentials = Deserialize<System.Collections.Generic.Dictionary<string, string>>(
            connectionPart.CredentialsJson.Text) ?? [];

        var context = new ShippingProviderConnectionContext(shipmentPart.ProviderConnectionId.Text, credentials);
        var request = new ShipmentPurchaseRequest(
            OrderId: shipmentPart.OrderId.Text ?? string.Empty,
            QuoteId: shipmentPart.QuoteId.Text ?? string.Empty,
            OriginAddress: origin,
            DestinationAddress: destination,
            Packages: packages,
            ServiceId: shipmentPart.ServiceId.Text ?? string.Empty);

        var response = await shipmentProvider.PurchaseShipmentAsync(context, request);

        // Store label if provided.
        if (response.LabelData is { Length: > 0 })
        {
            var documentId = await _documentStore.StoreDocumentAsync(
                shipmentContentItemId,
                response.LabelFormat ?? "PDF",
                response.LabelData);

            shipmentPart.LabelDocumentId = new OrchardCore.ContentFields.Fields.TextField { Text = documentId };
        }

        shipmentPart.TrackingNumber = new OrchardCore.ContentFields.Fields.TextField { Text = response.TrackingNumber };
        shipmentPart.TrackingUrl = new OrchardCore.ContentFields.Fields.TextField { Text = response.TrackingUrl };
        shipmentPart.PurchaseStatus = new OrchardCore.ContentFields.Fields.TextField
        {
            Text = ShipmentPurchaseStatus.Purchased.ToString(),
        };
        shipmentItem.Apply(shipmentPart);
        await _contentManager.UpdateAsync(shipmentItem);
        await _contentManager.PublishAsync(shipmentItem);

        workflowContext.Output["TrackingNumber"] = response.TrackingNumber;
        workflowContext.Output["TrackingUrl"] = response.TrackingUrl;

        return Outcomes("Done");
    }

    private static T? Deserialize<T>(string? json) where T : class
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try { return JsonSerializer.Deserialize<T>(json); }
        catch { return null; }
    }
}
