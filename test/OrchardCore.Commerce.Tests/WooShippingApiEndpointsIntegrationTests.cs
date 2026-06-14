using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Exceptions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Api;
using OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;
using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Tests;

public class WooShippingApiEndpointsIntegrationTests
{
    [Fact]
    public async Task WooRatesEndpoint_MapsProviderException_ToWooApiErrorEnvelope()
    {
        var shippingException = new ShippingProviderException(
            provider: "InPost",
            code: "shipx_validation_failed",
            message: "ShipX validation failed.",
            status: 422,
            providerType: "VALIDATION_ERROR",
            providerDetail: "Receiver postal code is invalid.",
            correlationId: "corr-123",
            errors: new Dictionary<string, string[]>
            {
                ["receiver.postal_code"] = ["is invalid"],
            });

        var shippingService = new ThrowingShippingService(shippingException);

        var request = new WooShippingRatesRequest
        {
            ShoppingCartId = "cart-woo-1",
            ShippingAddress = new WooAddressDto
            {
                Name = "Jane Doe",
                Street1 = "Main Street",
                City = "Warsaw",
                PostalCode = "00-000",
                Region = "PL",
            },
            CartSubtotal = 50,
            Currency = "PLN",
        };

        var method = typeof(WooShippingApiEndpoints).GetMethod(
            "GetShippingRatesAsync",
            BindingFlags.NonPublic | BindingFlags.Static);

        Assert.NotNull(method);

        var task = (Task<IResult>)method!.Invoke(null, [request, shippingService])!;
        var result = await task;

        var serviceProvider = WebApplication.CreateBuilder().Services.BuildServiceProvider();

        var context = new DefaultHttpContext
        {
            RequestServices = serviceProvider,
        };
        context.Response.Body = new MemoryStream();
        await result.ExecuteAsync(context);

        Assert.Equal(422, context.Response.StatusCode);

        context.Response.Body.Position = 0;
        using var responseDocument = await JsonDocument.ParseAsync(context.Response.Body);
        var root = responseDocument.RootElement;

        Assert.Equal("shipx_validation_failed", root.GetProperty("code").GetString());
        Assert.Equal("ShipX validation failed.", root.GetProperty("message").GetString());

        var data = root.GetProperty("data");
        Assert.Equal(422, data.GetProperty("status").GetInt32());
        Assert.Equal("InPost", data.GetProperty("provider").GetString());
        Assert.Equal("shipx_validation_failed", data.GetProperty("providerCode").GetString());
        Assert.Equal("VALIDATION_ERROR", data.GetProperty("providerType").GetString());
        Assert.Equal("Receiver postal code is invalid.", data.GetProperty("providerDetail").GetString());
        Assert.Equal("corr-123", data.GetProperty("correlationId").GetString());

        var errors = data.GetProperty("errors");
        Assert.True(errors.TryGetProperty("receiver.postal_code", out var postalCodeErrors));
        Assert.Equal("is invalid", postalCodeErrors.EnumerateArray().Single().GetString());
    }

    private sealed class ThrowingShippingService : IShippingService
    {
        private readonly Exception _exception;

        public ThrowingShippingService(Exception exception) => _exception = exception;

        public Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
            ShippingRateRequest request,
            CancellationToken cancellationToken = default) =>
            Task.FromException<IReadOnlyList<ShippingRateOption>>(_exception);

        public Task<ShippingRateSelectionResult> SelectRateAsync(
            ShippingRateSelectionRequest request,
            CancellationToken cancellationToken = default) =>
            Task.FromResult(new ShippingRateSelectionResult(true));

        public Task<ShippingSelection> GetSelectionAsync(
            string shoppingCartId,
            CancellationToken cancellationToken = default) =>
            Task.FromResult<ShippingSelection>(null);
    }
}
