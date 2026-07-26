using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Result of reconciling a shipment with carrier records.
/// </summary>
public sealed record ShipmentReconciliationResult(
    string ShipmentId,
    bool IsReconciled,
    string? Discrepancy = null,
    IDictionary<string, object>? ProviderData = null);

/// <summary>
/// Provider capability for reconciling shipments against carrier invoices.
/// </summary>
public interface IShipmentReconciliationProvider : IShippingProvider
{
    /// <summary>
    /// Reconciles a shipment against provider records (for billing verification).
    /// </summary>
    Task<ShipmentReconciliationResult> ReconcileShipmentAsync(
        ShippingProviderConnectionContext context,
        string shipmentId,
        DateTimeOffset billingPeriodStart,
        DateTimeOffset billingPeriodEnd,
        CancellationToken cancellationToken = default);
}
