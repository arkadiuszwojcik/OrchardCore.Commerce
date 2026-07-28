using OrchardCore.Modules.Manifest;

[assembly: Module(
    Name = "Orchard Core Commerce - InPost Shipping Provider",
    Author = "The Orchard Core Team",
    Website = "https://github.com/OrchardCMS/OrchardCore.Commerce",
    Version = "0.0.1",
    Description = "InPost shipping provider integration for OrchardCore Commerce.",
    Category = "Commerce"
)]

[assembly: Feature(
    Id = "OrchardCore.Commerce.Shipping.InPost",
    Name = "InPost Shipping Provider",
    Category = "Commerce",
    Description = "Integrates the InPost ShipX API for parcel lockers, courier services, rates, labels, and tracking.",
    Dependencies = new[] { "OrchardCore.Commerce.Shipping" }
)]
