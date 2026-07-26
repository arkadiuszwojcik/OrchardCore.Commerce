using OrchardCore.Modules.Manifest;

[assembly: Module(
    Name = "Orchard Core Commerce - DHL Shipping Provider",
    Author = "The Orchard Core Team",
    Website = "https://github.com/OrchardCMS/OrchardCore.Commerce",
    Version = "0.0.1",
    Description = "DHL shipping provider integration for OrchardCore Commerce.",
    Category = "Commerce"
)]

[assembly: Feature(
    Id = "OrchardCore.Commerce.Shipping.Dhl",
    Name = "DHL Shipping Provider",
    Category = "Commerce",
    Description = "Integrates DHL Express API for real-time rates, labels, and tracking.",
    Dependencies = new[] { "OrchardCore.Commerce.Shipping" }
)]
