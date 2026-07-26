namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Display metadata for showing a shipping option to customers.
/// </summary>
public sealed record ShippingDisplayInfo(
    string DisplayName,
    string? Description = null,
    string? IconUrl = null,
    int SortOrder = 0);
