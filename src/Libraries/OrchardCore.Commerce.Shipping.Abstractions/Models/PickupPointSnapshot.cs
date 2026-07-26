using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Snapshot of a pickup point (PUDO) selected for delivery.
/// </summary>
public sealed record PickupPointSnapshot(
    string PickupPointId,
    string? LocationName = null,
    AddressSnapshot? Address = null,
    IDictionary<string, string>? Metadata = null);
