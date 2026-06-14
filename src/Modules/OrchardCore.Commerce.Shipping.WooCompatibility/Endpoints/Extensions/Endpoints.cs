using Microsoft.AspNetCore.Routing;
using OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Api;

namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Extensions;

public static class Endpoints
{
    public static IEndpointRouteBuilder AddWooShippingCompatibilityEndpoints(this IEndpointRouteBuilder builder)
    {
        builder
            .AddWooShippingRatesEndpoint()
            .AddWooShippingSelectEndpoint()
            .AddWooShipmentUpdateWebhookEndpoint();

        return builder;
    }
}