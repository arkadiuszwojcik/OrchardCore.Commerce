using OrchardCore.Commerce;
using OrchardCore.Modules.Manifest;
using static OrchardCore.Commerce.Shipping.Constants.FeatureIds;

[assembly: Module(
    Name = "Orchard Core Commerce - Shipping",
    Author = "The Orchard Team",
    Website = "https://github.com/OrchardCMS/OrchardCore.Commerce",
    Version = "0.0.1",
    Description = "Shipping abstractions and services for Orchard Core Commerce.",
    Category = "Commerce"
)]

[assembly: Feature(
    Id = Shipping,
    Name = "Orchard Core Commerce - Shipping",
    Category = "Commerce",
    Description = "Provides shipping contracts and shipping service orchestration.",
    Dependencies = [CommerceConstants.Features.Core]
)]

[assembly: Feature(
    Id = FlatRateProvider,
    Name = "Orchard Core Commerce - Shipping - Flat Rate Provider",
    Category = "Commerce",
    Description = "Adds a development-safe flat-rate shipping provider.",
    Dependencies = [Shipping]
)]