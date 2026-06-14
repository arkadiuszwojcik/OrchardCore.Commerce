using OrchardCore.Modules.Manifest;
using static OrchardCore.Commerce.Shipping.WooCompatibility.Constants.FeatureIds;

[assembly: Module(
    Name = "Orchard Core Commerce - Shipping - Woo Compatibility",
    Author = "The Orchard Team",
    Website = "https://github.com/OrchardCMS/OrchardCore.Commerce",
    Version = "0.0.1",
    Description = "Woo-compatible Shipping API surface for Orchard Core Commerce.",
    Category = "Commerce"
)]

[assembly: Feature(
    Id = WooCompatibility,
    Name = "Orchard Core Commerce - Shipping - Woo Compatibility",
    Category = "Commerce",
    Description = "Exposes Woo-style shipping and webhook endpoints.",
    Dependencies = ["OrchardCore.Commerce.Shipping", "OrchardCore.Commerce.Shipping.InPost"]
)]