using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Routing;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.AddressDataType;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Exceptions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;
using OrchardCore.Commerce.Shipping.WooCompatibility.Options;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Api;

public static class WooShippingApiEndpoints
{
    public static IEndpointRouteBuilder AddWooShippingRatesEndpoint(this IEndpointRouteBuilder builder)
    {
        builder.MapPost("wp-json/inpost_pl/v1/shipping/rates", GetShippingRatesAsync);
        return builder;
    }

    public static IEndpointRouteBuilder AddWooShipmentUpdateWebhookEndpoint(this IEndpointRouteBuilder builder)
    {
        builder.MapPost("wp-json/inpost_pl/v1/order/update", UpdateOrderShipmentStatusAsync);
        return builder;
    }

    public static IEndpointRouteBuilder AddWooShippingSelectEndpoint(this IEndpointRouteBuilder builder)
    {
        builder.MapPost("wp-json/inpost_pl/v1/shipping/select", SelectShippingRateAsync);
        return builder;
    }

    private static async Task<IResult> GetShippingRatesAsync(
        [FromBody] WooShippingRatesRequest request,
        [FromServices] IShippingService shippingService)
    {
        if (request is null)
        {
            return TypedResults.BadRequest("Request body is required.");
        }

        var shippingAddress = request.ShippingAddress is null
            ? null
            : new Address
            {
                Name = request.ShippingAddress.Name,
                Company = request.ShippingAddress.Company,
                StreetAddress1 = request.ShippingAddress.Street1,
                StreetAddress2 = request.ShippingAddress.Street2,
                City = request.ShippingAddress.City,
                Province = request.ShippingAddress.Province,
                PostalCode = request.ShippingAddress.PostalCode,
                Region = request.ShippingAddress.Region,
            };

        IReadOnlyList<ShippingRateOption> rates;
        try
        {
            rates = await shippingService.GetRatesAsync(new ShippingRateRequest(
                request.ShoppingCartId,
                shippingAddress,
                request.CartSubtotal,
                request.Currency));
        }
        catch (ShippingProviderException ex)
        {
            var status = ex.Status is > 0 and <= 599 ? ex.Status.Value : 502;
            var errorResponse = new WooApiErrorResponse(
                Code: ex.Code,
                Message: ex.Message,
                Data: new WooApiErrorData(
                    Status: status,
                    Provider: ex.Provider,
                    ProviderCode: ex.Code,
                    ProviderType: ex.ProviderType,
                    ProviderDetail: ex.ProviderDetail,
                    CorrelationId: ex.CorrelationId,
                    Errors: ex.Errors));

                    return TypedResults.Json(errorResponse, statusCode: status);
        }

        var response = new WooShippingRatesResponse(
            CorrelationId: Guid.NewGuid().ToString("N"),
            Methods: rates
                .Select(rate => new WooShippingRateDto(
                    MethodId: rate.ExternalMethodCode ?? rate.MethodCode,
                    MethodTitle: rate.DisplayName,
                    Cost: rate.Price,
                    Currency: rate.Currency,
                    Provider: rate.Provider,
                    InternalMethodCode: rate.MethodCode))
                .ToList());

        return TypedResults.Ok(response);
    }

    private static Task<IResult> UpdateOrderShipmentStatusAsync(
        HttpContext httpContext,
        [FromBody] WooShipmentWebhookUpdateRequest request,
        [FromServices] IOptions<WooShippingCompatibilityApiOptions> options)
    {
        var configuredToken = options.Value.WebhookToken;
        if (!string.IsNullOrWhiteSpace(configuredToken))
        {
            var providedToken = httpContext.Request.Headers["X-InPost-Webhook-Token"].ToString();
            if (!string.Equals(providedToken, configuredToken, StringComparison.Ordinal))
            {
                return Task.FromResult<IResult>(TypedResults.Unauthorized());
            }
        }

        if (string.IsNullOrWhiteSpace(request?.OrderId) || string.IsNullOrWhiteSpace(request.Status))
        {
            return Task.FromResult<IResult>(TypedResults.BadRequest("OrderId and Status are required."));
        }

        // Processing pipeline will be connected to shipment state storage in the next implementation slice.
        var response = new
        {
            request.OrderId,
            request.ShipmentId,
            request.Status,
            Accepted = true,
        };

        return Task.FromResult<IResult>(TypedResults.Ok(response));
    }

    private static async Task<IResult> SelectShippingRateAsync(
        [FromBody] WooShippingSelectRequest request,
        [FromServices] IShippingService shippingService)
    {
        if (request is null)
        {
            return TypedResults.BadRequest(new WooShippingSelectResponse(false, "Request body is required."));
        }

        var methodCode = string.IsNullOrWhiteSpace(request.InternalMethodCode)
            ? request.MethodId
            : request.InternalMethodCode;

        var result = await shippingService.SelectRateAsync(new ShippingRateSelectionRequest(
            request.ShoppingCartId,
            request.Provider,
            methodCode,
            request.Cost,
            request.Currency,
            request.MethodTitle,
            request.MethodId));

        if (!result.Succeeded)
        {
            return TypedResults.BadRequest(new WooShippingSelectResponse(false, result.Message));
        }

        return TypedResults.Ok(new WooShippingSelectResponse(
            Success: true,
            ShoppingCartId: request.ShoppingCartId,
            MethodId: request.MethodId,
            Cost: request.Cost,
            Currency: request.Currency));
    }
}