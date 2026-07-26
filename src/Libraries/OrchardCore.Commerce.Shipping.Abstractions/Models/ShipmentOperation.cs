using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A single operation performed on a shipment (purchase, cancel, update tracking).
/// </summary>
public sealed record ShipmentOperation(
    string OperationType,
    DateTimeOffset PerformedAt,
    string? PerformedBy = null,
    string? Details = null);
