using OrchardCore.Commerce.MoneyDataType;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// A physical package to be shipped (result of packing algorithm).
/// </summary>
public sealed record ShippingPackage(
    string PackageId,
    IReadOnlyList<ShippingPackageLine> Lines,
    Weight TotalWeight,
    Dimensions? Dimensions = null,
    Amount? DeclaredValue = null,
    bool RequiresSignature = false,
    IDictionary<string, object>? Metadata = null);
