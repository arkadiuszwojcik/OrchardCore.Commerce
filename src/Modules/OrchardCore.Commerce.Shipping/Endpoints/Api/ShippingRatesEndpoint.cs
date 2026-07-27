using Lombiq.HelpfulLibraries.AspNetCore.Extensions;
using Lombiq.HelpfulLibraries.OrchardCore.Mvc;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Routing;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Permissions;
using System.Collections.Generic;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Endpoints.Api;

public static class ShippingRatesEndpoint
{
    public static IEndpointRouteBuilder AddShippingRatesEndpoint(this IEndpointRouteBuilder builder)
    {
        builder.MapPostWithDefaultSettings("api/commerce/shipping/rates", GetRatesAsync);
        return builder;
    }

    private static async Task<IResult> GetRatesAsync(
        [FromBody] ShippingRateRequest request,
        [FromServices] IAuthorizationService authorizationService,
        [FromServices] IShippingRateService rateService,
        HttpContext httpContext)
    {
        if (!await authorizationService.AuthorizeAsync(httpContext.User, ShippingPermissions.ManageShippingSettings))
        {
            return httpContext.ChallengeOrForbidApi();
        }

        var quotes = await rateService.GetAvailableRatesAsync(
            request.ShippingMethodId,
            request.OriginAddress,
            request.DestinationAddress,
            request.Packages);

        return TypedResults.Ok(quotes);
    }
}

/// <summary>
/// Request body for the shipping rates endpoint.
/// </summary>
public sealed record ShippingRateRequest(
    string ShippingMethodId,
    AddressSnapshot OriginAddress,
    AddressSnapshot DestinationAddress,
    IReadOnlyList<ShippingPackage> Packages);
