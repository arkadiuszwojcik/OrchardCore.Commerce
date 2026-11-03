#nullable enable
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.ContentFields.Fields;
using OrchardCore.ContentManagement;
using System;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using YesSql;

namespace OrchardCore.Commerce.Shipping.Services;

public enum ShipmentPurchaseOutcome
{
    Purchased,
    AlreadyPurchased,
    NotFound,
    Invalid,
    Blocked,
    Rejected,
    Uncertain,
}

public sealed record ShipmentPurchaseOutcomeResult(
    ShipmentPurchaseOutcome Outcome,
    string? Message = null,
    string? TrackingNumber = null,
    string? TrackingUrl = null)
{
    public bool IsSuccess => Outcome is ShipmentPurchaseOutcome.Purchased or ShipmentPurchaseOutcome.AlreadyPurchased;
}

/// <summary>
/// Purchases a shipment with an idempotency key persisted BEFORE the carrier call. A repeated request never
/// re-calls the carrier when a previous attempt succeeded or its result is unknown.
/// </summary>
public interface IShipmentPurchaseService
{
    Task<ShipmentPurchaseOutcomeResult> PurchaseAsync(string shipmentContentItemId, CancellationToken cancellationToken = default);
}

public class ShipmentPurchaseService : IShipmentPurchaseService
{
    private readonly IContentManager _contentManager;
    private readonly IShippingProviderRegistry _providerRegistry;
    private readonly IShippingDocumentStore _documentStore;
    private readonly IShippingCredentialsService _credentialsService;
    private readonly ISession _session;
    private readonly ILogger<ShipmentPurchaseService> _logger;

    public ShipmentPurchaseService(
        IContentManager contentManager,
        IShippingProviderRegistry providerRegistry,
        IShippingDocumentStore documentStore,
        IShippingCredentialsService credentialsService,
        ISession session,
        ILogger<ShipmentPurchaseService> logger)
    {
        _contentManager = contentManager;
        _providerRegistry = providerRegistry;
        _documentStore = documentStore;
        _credentialsService = credentialsService;
        _session = session;
        _logger = logger;
    }

    public async Task<ShipmentPurchaseOutcomeResult> PurchaseAsync(
        string shipmentContentItemId,
        CancellationToken cancellationToken = default)
    {
        var shipmentItem = await _contentManager.GetAsync(shipmentContentItemId);
        if (shipmentItem is null) return new(ShipmentPurchaseOutcome.NotFound, "Shipment not found.");

        if (!shipmentItem.TryGet<ShipmentPart>(out var shipmentPart))
            return new(ShipmentPurchaseOutcome.Invalid, "Not a shipment content item.");

        // Idempotency gate: only NotPurchased/Failed (a definite rejection) may call the carrier again.
        var status = Enum.TryParse<ShipmentPurchaseStatus>(shipmentPart.PurchaseStatus.Text, out var parsed)
            ? parsed
            : ShipmentPurchaseStatus.NotPurchased;
        switch (status)
        {
            case ShipmentPurchaseStatus.Purchased:
                return new(
                    ShipmentPurchaseOutcome.AlreadyPurchased,
                    TrackingNumber: shipmentPart.TrackingNumber.Text,
                    TrackingUrl: shipmentPart.TrackingUrl.Text);
            case ShipmentPurchaseStatus.Pending:
            case ShipmentPurchaseStatus.Uncertain:
                return new(
                    ShipmentPurchaseOutcome.Blocked,
                    "A previous purchase attempt has an unknown result. Reconcile it with the carrier before retrying.");
            case ShipmentPurchaseStatus.Cancelled:
                return new(ShipmentPurchaseOutcome.Blocked, "The shipment was cancelled.");
        }

        var connectionItem = await _contentManager.GetAsync(shipmentPart.ProviderConnectionId.Text);
        if (connectionItem is null || !connectionItem.TryGet<ShippingProviderConnectionPart>(out var connectionPart))
            return new(ShipmentPurchaseOutcome.Invalid, "Provider connection not found.");

        if (_providerRegistry.GetProvider(connectionPart.ProviderKey.Text) is not IShipmentProvider provider)
            return new(ShipmentPurchaseOutcome.Invalid, "Provider not registered or does not support label purchase.");

        var origin = TryDeserialize<AddressSnapshot>(shipmentPart.OriginAddressJson.Text);
        var destination = TryDeserialize<AddressSnapshot>(shipmentPart.DestinationAddressJson.Text);
        var packages = TryDeserialize<List<ShippingPackage>>(shipmentPart.PackagesJson.Text) ?? [];
        if (origin is null || destination is null)
            return new(ShipmentPurchaseOutcome.Invalid, "Origin or destination address missing.");

        var connectionId = shipmentPart.ProviderConnectionId.Text;
        var credentials = await _credentialsService.GetCredentialsAsync(connectionId);
        var context = new ShippingProviderConnectionContext(
            connectionId,
            credentials,
            ProviderKey: connectionPart.ProviderKey.Text,
            ConfigurationRevision: (long)(connectionPart.ConfigurationRevision.Value ?? 0),
            CredentialVersion: (long)(connectionPart.CredentialVersion.Value ?? 0),
            IsTestMode: connectionPart.IsTestMode.Value);

        // Persist the key and Pending state before the external call.
        var idempotencyKey = string.IsNullOrEmpty(shipmentPart.IdempotencyKey.Text)
            ? Guid.NewGuid().ToString("N")
            : shipmentPart.IdempotencyKey.Text;
        shipmentPart.IdempotencyKey = new TextField { Text = idempotencyKey };
        shipmentPart.PurchaseStatus = new TextField { Text = nameof(ShipmentPurchaseStatus.Pending) };
        await SaveAsync(shipmentItem, shipmentPart, cancellationToken);

        var request = new ShipmentPurchaseRequest(
            OrderId: shipmentPart.OrderId.Text ?? string.Empty,
            QuoteId: shipmentPart.QuoteId.Text ?? string.Empty,
            OriginAddress: origin,
            DestinationAddress: destination,
            Packages: packages,
            ServiceId: shipmentPart.ServiceId.Text ?? string.Empty,
            IdempotencyKey: idempotencyKey);

        ShipmentPurchaseResponse response;
        try
        {
            response = await provider.PurchaseShipmentAsync(context, request, cancellationToken);
        }
        catch (ShippingProviderRejectedException exception)
        {
            _logger.LogWarning(exception, "Carrier rejected the purchase of shipment {ShipmentId}.", shipmentContentItemId);
            shipmentPart.PurchaseStatus = new TextField { Text = nameof(ShipmentPurchaseStatus.Failed) };
            await SaveAsync(shipmentItem, shipmentPart, CancellationToken.None);
            return new(ShipmentPurchaseOutcome.Rejected, exception.Message);
        }
        catch (Exception exception)
        {
            // Timeout, cancellation, connection loss or a bug: a shipment may exist at the carrier.
            _logger.LogError(exception, "Purchase of shipment {ShipmentId} has an uncertain result.", shipmentContentItemId);
            shipmentPart.PurchaseStatus = new TextField { Text = nameof(ShipmentPurchaseStatus.Uncertain) };
            await SaveAsync(shipmentItem, shipmentPart, CancellationToken.None);
            return new(ShipmentPurchaseOutcome.Uncertain, "The carrier result is unknown; automatic retry is blocked.");
        }

        if (response.LabelData is { Length: > 0 })
        {
            var documentId = await _documentStore.StoreDocumentAsync(
                shipmentContentItemId, response.LabelFormat ?? "PDF", response.LabelData);
            shipmentPart.LabelDocumentId = new TextField { Text = documentId };
        }

        shipmentPart.ShipmentId = new TextField { Text = response.ShipmentId };
        shipmentPart.TrackingNumber = new TextField { Text = response.TrackingNumber };
        shipmentPart.TrackingUrl = new TextField { Text = response.TrackingUrl };
        shipmentPart.PurchaseStatus = new TextField { Text = nameof(ShipmentPurchaseStatus.Purchased) };
        await SaveAsync(shipmentItem, shipmentPart, CancellationToken.None);

        return new(ShipmentPurchaseOutcome.Purchased, TrackingNumber: response.TrackingNumber, TrackingUrl: response.TrackingUrl);
    }

    private async Task SaveAsync(ContentItem item, ShipmentPart part, CancellationToken cancellationToken)
    {
        item.Apply(part);
        await _contentManager.UpdateAsync(item);
        await _contentManager.PublishAsync(item);
        await _session.SaveChangesAsync(cancellationToken);
    }

    private static T? TryDeserialize<T>(string? json) where T : class
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try { return JsonSerializer.Deserialize<T>(json); }
        catch (JsonException) { return null; }
    }
}
