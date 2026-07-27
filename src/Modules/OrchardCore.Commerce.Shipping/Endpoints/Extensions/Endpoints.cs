using Microsoft.AspNetCore.Routing;
using OrchardCore.Commerce.Shipping.Endpoints.Api;

namespace OrchardCore.Commerce.Shipping.Endpoints.Extensions;

public static class Endpoints
{
    public static IEndpointRouteBuilder AddShippingApiEndpoints(this IEndpointRouteBuilder router)
    {
        router
            .AddShippingRatesEndpoint()
            .AddShipmentsEndpoints();

        return router;
    }
}
