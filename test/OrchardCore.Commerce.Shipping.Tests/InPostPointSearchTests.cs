using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Commerce.Shipping.InPost.Client.Models;
using OrchardCore.Commerce.Shipping.InPost.Models;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Threading;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class InPostPointSearchTests
{
    private static readonly ShippingProviderConnectionContext _context = new(
        "c1",
        new Dictionary<string, string> { ["ApiToken"] = "t" },
        Metadata: new Dictionary<string, object>
        {
            [nameof(InPostConnectionSettings)] = new InPostConnectionSettings { OrganizationId = "1" },
        },
        IsTestMode: true);

    // A point as returned by the Points API (sandbox): "virtual" is a string, display_name may be missing.
    private const string PointJson = """
        {
          "href": "https://sandbox-api-pl-points.easypack24.net/v1/points/KRA02APP",
          "name": "KRA02APP",
          "type": ["parcel_locker"],
          "status": "Operating",
          "location": { "longitude": 19.87325, "latitude": 50.00919 },
          "location_type": "Outdoor",
          "location_date": null,
          "location_description": "Prywatna posesja",
          "location_description_1": "Opis 1",
          "location_description_2": null,
          "distance": 400,
          "opening_hours": "24/7",
          "address": { "line1": "Dobrowolskiego", "line2": "30-394 Kraków" },
          "address_details": {
            "city": "Kraków", "province": "małopolskie", "post_code": "30-394",
            "street": "Dobrowolskiego", "building_number": null, "flat_number": null
          },
          "phone_number": null,
          "payment_point_descr": "Płatność apką InPost",
          "functions": ["parcel", "parcel_collect"],
          "partner_id": 0,
          "is_next": false,
          "payment_available": true,
          "payment_type": { "0": "Payments are not supported" },
          "virtual": "0",
          "recommended_low_interest_box_machines_list": null,
          "apm_doubled": null,
          "location_247": true,
          "operating_hours_extended": { "customer": null },
          "agency": null,
          "image_url": "https://static.easypack24.net/points/pl/images/KRA02APP.jpg",
          "easy_access_zone": true,
          "air_index_level": null,
          "physical_type": "screenless",
          "physical_type_mapped": "006",
          "physical_type_description": "Appkomat",
          "supported_locker_temperatures": [4, 20],
          "last_updated_date": { "seconds": 1777012656, "nanos": 953000000 }
        }
        """;

    [Fact]
    public void EmptySearchHasNoParameters() => Assert.Empty(new InPostPointSearch().ToQuery());

    [Fact]
    public void ParametersFollowThePointsNamesAndFormats()
    {
        var search = new InPostPointSearch
        {
            Names = ["KRA010", "ADA01N"],
            Types = [InPostPointType.ParcelLocker, InPostPointType.ParcelLockerSuperpop],
            Functions = [InPostPointFunction.Parcel, InPostPointFunction.ParcelReverseReturnSend],
            PartnerIds = [0, 33],
            IsNext = true,
            PaymentAvailable = false,
            PostCodes = ["11-111"],
            Cities = ["Kraków", "Warszawa"],
            Provinces = ["Małopolska"],
            Virtual = [0, 1],
            UpdatedFrom = new DateOnly(2018, 4, 24),
            UpdatedTo = new DateOnly(2018, 4, 26),
            Location247 = true,
            SupportedLockerTemperature = 20,
            RelativePoint = new InPostGeoPoint(52.123, 19.321),
            MaxDistance = 10000.5,
            Limit = 10,
            SortBy = InPostPointSortBy.DistanceToRelativePoint,
            SortOrder = InPostSortOrder.Descending,
            Page = 2,
            PerPage = 100,
        };

        Assert.Equal(
            new Dictionary<string, string>
            {
                ["name"] = "KRA010,ADA01N",
                ["type"] = "parcel_locker,parcel_locker_superpop",
                ["functions"] = "parcel,parcel_reverse_return_send",
                ["partner_id"] = "0,33",
                ["is_next"] = "true",
                ["payment_available"] = "false",
                ["post_code"] = "11-111",
                ["city"] = "Kraków,Warszawa",
                ["province"] = "Małopolska",
                ["virtual"] = "0,1",
                ["updated_from"] = "2018-04-24",
                ["updated_to"] = "2018-04-26",
                ["location_247"] = "true",
                ["supported_locker_temperatures"] = "20",
                ["relative_point"] = "52.123,19.321",
                ["max_distance"] = "10000.5",
                ["limit"] = "10",
                ["sort_by"] = "distance_to_relative_point",
                ["sort_order"] = "desc",
                ["page"] = "2",
                ["per_page"] = "100",
            },
            search.ToQuery());
    }

    [Fact]
    public void EveryTypeAndFunctionHasItsDocumentedValue()
    {
        string[] types = ["parcel_locker", "pop", "parcel_locker_only", "parcel_locker_superpop"];
        string[] functions =
        [
            "parcel", "parcel_send", "parcel_collect", "parcel_reverse_return_send", "standard_letter_collect",
            "standard_letter_send", "allegro_parcel_collect", "allegro_parcel_send", "allegro_parcel_reverse_return_send",
            "allegro_letter_collect", "allegro_letter_send", "allegro_letter_reverse_return_send",
            "allegro_courier_collect", "allegro_courier_send", "allegro_courier_reverse_return_send",
            "standard_courier_collect", "standard_courier_send", "standard_courier_reverse_return_send",
            "air_on_airport", "air_outside_airport", "cool_parcel_collect", "laundry", "avizo",
        ];

        Assert.Equal(
            types,
            Enum.GetValues<InPostPointType>().Select(type => new InPostPointSearch { Types = [type] }.ToQuery()["type"]));
        Assert.Equal(
            functions,
            Enum.GetValues<InPostPointFunction>().Select(function => new InPostPointSearch { Functions = [function] }.ToQuery()["functions"]));
        Assert.Equal(
            ["name", "distance_to_relative_point", "status"],
            Enum.GetValues<InPostPointSortBy>().Select(sortBy => new InPostPointSearch { SortBy = sortBy }.ToQuery()["sort_by"]));
    }

    [Fact]
    public void DocumentedLimitsAreEnforced()
    {
        Assert.Throws<InvalidOperationException>(() =>
            new InPostPointSearch { UpdatedTo = new DateOnly(2018, 4, 26) }.ToQuery());
        Assert.Throws<InvalidOperationException>(() => new InPostPointSearch { MaxDistance = 50001 }.ToQuery());
        Assert.Throws<InvalidOperationException>(() => new InPostPointSearch { MaxDistance = 0 }.ToQuery());
        Assert.Throws<InvalidOperationException>(() => new InPostPointSearch { PerPage = 501 }.ToQuery());
        Assert.Throws<InvalidOperationException>(() => new InPostPointSearch { PerPage = 0 }.ToQuery());

        // The limits themselves are allowed.
        new InPostPointSearch { MaxDistance = 50000, PerPage = 500 }.ToQuery();
    }

    [Fact]
    public async Task SearchReadsThePointsAndTheEnvelope()
    {
        using var handler = new RecordingHandler($$"""
            { "href": "x", "count": 24797, "page": 3, "per_page": 1, "total_pages": 24797, "items": [{{PointJson}}] }
            """);
        var client = CreateClient(handler);

        var page = await client.SearchPointsAsync(
            _context,
            new InPostPointSearch { RelativePostCode = "30-394", Page = 3, PerPage = 1 },
            TestContext.Current.CancellationToken);

        Assert.Equal(24797, page.Count);
        Assert.Equal(3, page.Page);
        Assert.Equal(1, page.PerPage);
        Assert.Equal(24797, page.TotalPages);

        var point = Assert.Single(page.Items);
        Assert.Equal("KRA02APP", point.Name);
        Assert.Null(point.DisplayName);
        Assert.Equal(["parcel_locker"], point.Type);
        Assert.Equal("Operating", point.Status);
        Assert.Equal(new InPostGeoPoint(50.00919, 19.87325), point.Location);
        Assert.Equal("Opis 1", point.LocationDescription1);
        Assert.Null(point.LocationDescription2);
        Assert.Equal(400, point.Distance);
        Assert.Equal("24/7", point.OpeningHours);
        Assert.Equal(new InPostPointAddress("Dobrowolskiego", "30-394 Kraków"), point.Address);
        Assert.Equal("30-394", point.AddressDetails?.PostCode);
        Assert.Equal("Kraków", point.AddressDetails?.City);
        Assert.Equal(["parcel", "parcel_collect"], point.Functions);
        Assert.Equal(0, point.PartnerId);
        Assert.Equal(false, point.IsNext);
        Assert.Equal(true, point.PaymentAvailable);
        Assert.Equal(0, point.Virtual);
        Assert.Equal(true, point.Location247);
        Assert.Equal(true, point.EasyAccessZone);
        Assert.Equal("006", point.PhysicalTypeMapped);
        Assert.Equal([4, 20], point.SupportedLockerTemperatures);

        var uri = Assert.Single(handler.Requests).RequestUri!;
        Assert.Equal("sandbox-api-gateway-pl.easypack24.net", uri.Host);
        Assert.Equal("/v1/points", uri.AbsolutePath);
        Assert.Equal("?relative_post_code=30-394&page=3&per_page=1", uri.Query);
    }

    [Fact]
    public async Task PointsGoToTheirOwnGatewayAndOtherCallsToShipX()
    {
        using var handler = new RecordingHandler("{}");
        var client = CreateClient(handler);

        await client.SearchPointsAsync(_context, new InPostPointSearch(), TestContext.Current.CancellationToken);
        await client.SearchPointsAsync(_context with { IsTestMode = false }, new InPostPointSearch(), TestContext.Current.CancellationToken);
        await client.GetOrganizationAsync(_context, TestContext.Current.CancellationToken);

        Assert.Equal("https://sandbox-api-gateway-pl.easypack24.net/v1/points", handler.Requests[0].RequestUri?.ToString());
        Assert.Equal("https://api.inpost.pl/v1/points", handler.Requests[1].RequestUri?.ToString());
        Assert.Equal("https://sandbox-api-shipx-pl.easypack24.net/v1/organizations/1", handler.Requests[2].RequestUri?.ToString());
        Assert.All(handler.Requests, request => Assert.Equal("t", request.Headers.Authorization?.Parameter));
    }

    [Fact]
    public async Task PointDetailsAreASearchByName()
    {
        using var handler = new RecordingHandler($$"""{ "count": 1, "items": [{{PointJson}}] }""");
        var client = CreateClient(handler);

        var point = await client.GetPointAsync(_context, "KRA02APP", TestContext.Current.CancellationToken);

        Assert.Equal("KRA02APP", point?.Name);
        Assert.Equal("?name=KRA02APP", Assert.Single(handler.Requests).RequestUri!.Query);
    }

    [Fact]
    public async Task UnknownPointIsNull()
    {
        using var handler = new RecordingHandler("""{ "count": 0, "items": [] }""");
        var client = CreateClient(handler);

        Assert.Null(await client.GetPointAsync(_context, "NOPE", TestContext.Current.CancellationToken));
    }

    private static InPostApiClient CreateClient(HttpMessageHandler handler)
    {
        var services = new ServiceCollection();
        services.AddInPostApiClient().ConfigurePrimaryHttpMessageHandler(() => handler);
        return services.BuildServiceProvider().GetRequiredService<InPostApiClient>();
    }

    private sealed class RecordingHandler(string body) : HttpMessageHandler
    {
        public List<HttpRequestMessage> Requests { get; } = [];

        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            Requests.Add(request);
            return Task.FromResult(new HttpResponseMessage(HttpStatusCode.OK) { Content = new StringContent(body) });
        }
    }
}
