using Microsoft.Extensions.Logging.Abstractions;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.AddressDataType;
using OrchardCore.Commerce.Shipping.InPost.Options;
using OrchardCore.Commerce.Shipping.InPost.Services;
using OrchardCore.Commerce.Shipping.Models;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Tests;

public class InPostShippingClientIntegrationTests
{
    [Fact]
    public async Task GetRatesAsync_BuildsCalculatePayload_AndParsesShipXResponseShapes()
    {
        var options = new InPostShippingOptions
        {
            ApiToken = "token",
            OrganizationId = 123,
            Environment = InPostShippingOptions.EnvironmentSandbox,
            Country = InPostShippingOptions.CountryPl,
            Currency = "PLN",
            SenderFirstName = "Store",
            SenderLastName = "Owner",
            SenderEmail = "store@example.com",
            SenderPhone = "500600700",
            SenderCompanyName = "My Store",
            SenderStreet = "Sender Street",
            SenderBuildingNumber = "10A",
            SenderPostalCode = "00-111",
            SenderCity = "Warsaw",
            SenderCountryCode = InPostShippingOptions.CountryPl,
            DefaultParcelWeightKg = 2.5m,
            DefaultParcelLengthMm = 300m,
            DefaultParcelWidthMm = 200m,
            DefaultParcelHeightMm = 100m,
            DefaultLockerPointId = "WAW01M",
        };

        var handler = new RecordingShipXMessageHandler();
        var factory = new TestHttpClientFactory(new HttpClient(handler));

        var client = new InPostShippingClient(
            factory,
            Options.Create(options),
            NullLogger<InPostShippingClient>.Instance);

        var request = new ShippingRateRequest(
            ShoppingCartId: "cart-1",
            ShippingAddress: new Address
            {
                Name = "Jane Doe",
                Company = "Buyer Co",
                StreetAddress1 = "Recipient Street",
                StreetAddress2 = "7",
                City = "Krakow",
                PostalCode = "31-001",
                Region = "PL",
            },
            CartSubtotal: 199.99m,
            Currency: "PLN");

        var rates = await client.GetRatesAsync(request);

        Assert.Equal(2, rates.Count);

        var lockerRate = rates.Single(rate => rate.MethodCode == "inpost_parcel_locker");
        Assert.Equal(12.34m, lockerRate.Price);
        Assert.Equal("InPost Paczkomat", lockerRate.DisplayName);

        var courierRate = rates.Single(rate => rate.MethodCode == "inpost_courier");
        Assert.Equal(15.67m, courierRate.Price);
        Assert.Equal("InPost Courier", courierRate.DisplayName);

        Assert.True(handler.CalculatePayloadByService.TryGetValue("inpost_locker_standard", out var lockerPayloadJson));
        using (var lockerPayload = JsonDocument.Parse(lockerPayloadJson))
        {
            var root = lockerPayload.RootElement;
            Assert.Equal("inpost_locker_standard", root.GetProperty("service").GetString());
            Assert.Equal("cart-1", root.GetProperty("reference").GetString());

            var customAttributes = root.GetProperty("customAttributes");
            Assert.Equal("parcel_locker", customAttributes.GetProperty("sendingMethod").GetString());
            Assert.Equal("WAW01M", customAttributes.GetProperty("targetPoint").GetString());

            var destination = root.GetProperty("destination");
            Assert.Equal("WAW01M", destination.GetProperty("pointId").GetString());
            Assert.Equal("PL", destination.GetProperty("countryCode").GetString());

            var parcels = root.GetProperty("parcels");
            Assert.Equal(JsonValueKind.Array, parcels.ValueKind);
            var parcel = parcels.EnumerateArray().Single();
            Assert.Equal("STANDARD", parcel.GetProperty("type").GetString());
            Assert.Equal(300m, parcel.GetProperty("dimensions").GetProperty("length").GetDecimal());
            Assert.Equal(2.5m, parcel.GetProperty("weight").GetProperty("amount").GetDecimal());
        }

        Assert.True(handler.CalculatePayloadByService.TryGetValue("inpost_courier_standard", out var courierPayloadJson));
        using (var courierPayload = JsonDocument.Parse(courierPayloadJson))
        {
            var root = courierPayload.RootElement;
            Assert.Equal("inpost_courier_standard", root.GetProperty("service").GetString());

            var customAttributes = root.GetProperty("customAttributes");
            Assert.Equal("dispatch_order", customAttributes.GetProperty("sendingMethod").GetString());
            Assert.False(customAttributes.TryGetProperty("targetPoint", out _));

            var destination = root.GetProperty("destination");
            Assert.Equal("Recipient Street", destination.GetProperty("street").GetString());
            Assert.Equal("7", destination.GetProperty("houseNumber").GetString());
            Assert.Equal("Krakow", destination.GetProperty("city").GetString());
            Assert.Equal("31-001", destination.GetProperty("postalCode").GetString());
            Assert.Equal("PL", destination.GetProperty("countryCode").GetString());
        }
    }

    private sealed class RecordingShipXMessageHandler : HttpMessageHandler
    {
        public Dictionary<string, string> CalculatePayloadByService { get; } = new(StringComparer.OrdinalIgnoreCase);

        protected override async Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            var path = request.RequestUri?.AbsolutePath;

            if (request.Method == HttpMethod.Get && path == "/organizations/123")
            {
                return JsonResponse("""
                    {
                      "services": ["inpost_locker_standard", "inpost_courier_standard"]
                    }
                    """);
            }

            if (request.Method == HttpMethod.Get && path == "/services")
            {
                return JsonResponse("""
                    [
                      {
                        "id": "inpost_locker_standard",
                        "name": "InPost Paczkomat",
                        "description": "Locker delivery"
                      },
                      {
                        "id": "inpost_courier_standard",
                        "name": "InPost Courier",
                        "description": "Courier delivery"
                      }
                    ]
                    """);
            }

            if (request.Method == HttpMethod.Post && path == "/organizations/123/shipments/calculate")
            {
                var payload = await request.Content!.ReadAsStringAsync(cancellationToken);
                using var payloadDocument = JsonDocument.Parse(payload);
                var service = payloadDocument.RootElement.GetProperty("service").GetString()!;
                CalculatePayloadByService[service] = payload;

                return service switch
                {
                    "inpost_locker_standard" => JsonResponse("""
                        {
                                                    "total": { "amount": 12.34 }
                        }
                        """),
                    "inpost_courier_standard" => JsonResponse("""
                        {
                                                    "calculated_charge_amount_non_commission": 15.67
                        }
                        """),
                    _ => JsonResponse("{}", HttpStatusCode.BadRequest),
                };
            }

            return JsonResponse("{}", HttpStatusCode.NotFound);
        }

        private static HttpResponseMessage JsonResponse(string body, HttpStatusCode statusCode = HttpStatusCode.OK) =>
            new(statusCode)
            {
                Content = new StringContent(body, Encoding.UTF8, "application/json"),
            };
    }

    private sealed class TestHttpClientFactory : IHttpClientFactory
    {
        private readonly HttpClient _client;

        public TestHttpClientFactory(HttpClient client) => _client = client;

        public HttpClient CreateClient(string name = "") => _client;
    }
}
