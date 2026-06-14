namespace OrchardCore.Commerce.Shipping.Constants;

public static class FeatureIds
{
    public const string Area = "OrchardCore.Commerce.Shipping";

    public const string Shipping = Area;
    public const string FlatRateProvider = $"{Area}.{nameof(FlatRateProvider)}";
}