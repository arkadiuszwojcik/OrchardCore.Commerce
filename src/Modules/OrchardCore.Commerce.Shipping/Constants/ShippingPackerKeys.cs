namespace OrchardCore.Commerce.Shipping;

/// <summary>
/// Well-known metadata keys for packing algorithms.
/// </summary>
public static class ShippingPackerKeys
{
    /// <summary>
    /// Metadata key indicating a product cannot be shipped separately.
    /// </summary>
    public const string RequiresSeparatePackage = "RequiresSeparatePackage";

    /// <summary>
    /// Metadata key for maximum quantity per package.
    /// </summary>
    public const string MaxQuantityPerPackage = "MaxQuantityPerPackage";

    /// <summary>
    /// Metadata key for product groups that must ship together.
    /// </summary>
    public const string PackingGroup = "PackingGroup";
}
