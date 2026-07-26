using OrchardCore.Modules.Manifest;

[assembly: Module(
    Name = "Orchard Core Commerce - Shipping",
    Author = "The Orchard Core Team",
    Website = "https://github.com/OrchardCMS/OrchardCore.Commerce",
    Version = "0.0.1",
    Description = "Shipping subsystem for Orchard Core Commerce with provider-neutral rate quoting, fulfillment, and tracking.",
    Category = "Commerce"
)]

[assembly: Feature(
    Id = "OrchardCore.Commerce.Shipping",
    Name = "Orchard Core Commerce - Shipping (Core)",
    Category = "Commerce",
    Description = "Core shipping functionality: provider connections, methods, origins, quotes, shipments, and integration.",
    Dependencies = new[]
    {
        "OrchardCore.Contents",
        "OrchardCore.Commerce",
    }
)]

[assembly: Feature(
    Id = "OrchardCore.Commerce.Shipping.Api",
    Name = "Orchard Core Commerce - Shipping API",
    Category = "Commerce",
    Description = "RESTful API endpoints for shipping rate queries, quote retrieval, and shipment operations.",
    Dependencies = new[]
    {
        "OrchardCore.Commerce.Shipping",
        "OrchardCore.Apis",
    }
)]

[assembly: Feature(
    Id = "OrchardCore.Commerce.Shipping.Workflows",
    Name = "Orchard Core Commerce - Shipping Workflows",
    Category = "Commerce",
    Description = "Workflow activities and events for shipping: quote fetched, shipment purchased, tracking updated, delivery confirmed.",
    Dependencies = new[]
    {
        "OrchardCore.Commerce.Shipping",
        "OrchardCore.Workflows",
    }
)]
