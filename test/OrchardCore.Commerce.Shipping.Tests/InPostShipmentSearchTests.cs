using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Commerce.Shipping.InPost.Client.Models;
using OrchardCore.Commerce.Shipping.InPost.Models;
using System;
using System.Collections.Generic;
using System.Net;
using System.Net.Http;
using System.Threading;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class InPostShipmentSearchTests
{
    private static readonly ShippingProviderConnectionContext _context = new(
        "c1",
        new Dictionary<string, string> { ["ApiToken"] = "t" },
        Metadata: new Dictionary<string, object>
        {
            [nameof(InPostConnectionSettings)] = new InPostConnectionSettings { OrganizationId = "1" },
        },
        IsTestMode: true);

    [Fact]
    public void EmptySearchHasNoParameters() => Assert.Empty(new InPostShipmentSearch().ToQuery());

    [Fact]
    public void ParametersFollowTheShipXNamesAndFormats()
    {
        var search = new InPostShipmentSearch
        {
            Ids = [1, 2],
            CreatedOn = new DateOnly(2016, 1, 7),
            CreatedFrom = new DateTimeOffset(2016, 1, 1, 13, 10, 0, TimeSpan.FromHours(1)),
            TrackingNumberContains = "5318",
            Statuses = ["confirmed", "offers_prepared"],
            Carriers = ["InPost Kurier"],
            InsuranceAmountFrom = 50.5m,
            CodAmountTo = 100,
            Receiver = new InPostPartySearch { Name = "smith", PostCode = "30-442" },
            Sender = new InPostPartySearch { City = "Kraków" },
            Monitoring = true,
            OnlyChoiceActiveOffers = false,
            SortBy = InPostShipmentSortBy.CreatedAt,
            SortOrder = InPostSortOrder.Descending,
            Page = 2,
            PerPage = 25,
        };

        var query = search.ToQuery();

        Assert.Equal(
            new Dictionary<string, string>
            {
                ["id"] = "1,2",
                ["created_at"] = "2016-01-07",
                ["created_at_gteq"] = "2016-01-01T13:10:00.0000000+01:00",
                ["tracking_number_cont"] = "5318",
                ["status"] = "confirmed,offers_prepared",
                ["carrier"] = "InPost Kurier",
                ["insurance_amount_gteq"] = "50.5",
                ["cod_amount_lteq"] = "100",
                ["receiver_name"] = "smith",
                ["receiver_post_code"] = "30-442",
                ["sender_city"] = "Kraków",
                ["monitoring"] = "true",
                ["only_choice_active_offers"] = "false",
                ["sort_by"] = "created_at",
                ["sort_order"] = "desc",
                ["page"] = "2",
                ["per_page"] = "25",
            },
            query);
    }

    [Theory]
    [InlineData(InPostShipmentSortBy.Id, "id")]
    [InlineData(InPostShipmentSortBy.CreatedAt, "created_at")]
    [InlineData(InPostShipmentSortBy.TrackingNumber, "tracking_number")]
    [InlineData(InPostShipmentSortBy.Service, "service")]
    [InlineData(InPostShipmentSortBy.Status, "status")]
    [InlineData(InPostShipmentSortBy.InsuranceAmount, "insurance_amount")]
    [InlineData(InPostShipmentSortBy.CodAmount, "cod_amount")]
    [InlineData(InPostShipmentSortBy.ExternalCustomerId, "external_customer_id")]
    public void EverySortAttributeHasItsShipXValue(InPostShipmentSortBy sortBy, string expected) =>
        Assert.Equal(expected, new InPostShipmentSearch { SortBy = sortBy }.ToQuery()["sort_by"]);

    [Theory]
    [InlineData(InPostSortOrder.Ascending, "asc")]
    [InlineData(InPostSortOrder.Descending, "desc")]
    public void SortOrderHasItsShipXValue(InPostSortOrder order, string expected) =>
        Assert.Equal(expected, new InPostShipmentSearch { SortOrder = order }.ToQuery()["sort_order"]);

    [Fact]
    public async Task SearchSendsTheQueryAndReadsTheCollectionEnvelope()
    {
        using var handler = new RecordingHandler("""
            {
              "count": 7, "page": 2, "per_page": 1,
              "items": [{ "id": 5, "status": "confirmed", "tracking_number": "T1", "service": "inpost_locker_standard" }]
            }
            """);
        var client = CreateClient(handler);

        var page = await client.SearchShipmentsAsync(
            _context,
            new InPostShipmentSearch { Statuses = ["confirmed"], Page = 2, PerPage = 1 },
            TestContext.Current.CancellationToken);

        Assert.Equal(7, page.Count);
        Assert.Equal(2, page.Page);
        Assert.Equal(1, page.PerPage);
        var shipment = Assert.Single(page.Items);
        Assert.Equal(5, shipment.Id);
        Assert.Equal("T1", shipment.TrackingNumber);

        var uri = Assert.Single(handler.Requests).RequestUri!;
        Assert.Equal("/v1/organizations/1/shipments", uri.AbsolutePath);
        Assert.Equal("?status=confirmed&page=2&per_page=1", uri.Query);
    }

    [Fact]
    public async Task BareArrayResponseHasNoCounters()
    {
        using var handler = new RecordingHandler("""[{ "id": 5 }]""");
        var client = CreateClient(handler);

        var page = await client.SearchShipmentsAsync(
            _context, new InPostShipmentSearch(), TestContext.Current.CancellationToken);

        Assert.Single(page.Items);
        Assert.Null(page.Count);
        Assert.Null(page.Page);
    }

    [Fact]
    public async Task TrackingLookupFiltersByTrackingNumber()
    {
        using var handler = new RecordingHandler("""{ "items": [{ "id": 9, "tracking_number": "T9" }] }""");
        var client = CreateClient(handler);

        var shipment = await client.FindShipmentByTrackingNumberAsync(_context, "T9", TestContext.Current.CancellationToken);

        Assert.Equal(9, shipment?.Id);
        Assert.Equal("?tracking_number=T9", Assert.Single(handler.Requests).RequestUri!.Query);
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
