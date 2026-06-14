using OrchardCore.Modules.Manifest;
using static OrchardCore.Commerce.Shipping.InPost.Constants.FeatureIds;

[assembly: Module(
    Name = "Orchard Core Commerce - Shipping - InPost",
    Author = "The Orchard Team",
    Website = "https://github.com/OrchardCMS/OrchardCore.Commerce",
    Version = "0.0.1",
    Description = "InPost shipping provider for Orchard Core Commerce.",
    Category = "Commerce"
)]

[assembly: Feature(
    Id = InPost,
    Name = "Orchard Core Commerce - Shipping - InPost",
    Category = "Commerce",
    Description = "Registers InPost shipping provider and settings.",
    Dependencies = ["OrchardCore.Commerce.Shipping"]
)]