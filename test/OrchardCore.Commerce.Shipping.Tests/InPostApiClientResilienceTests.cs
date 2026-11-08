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
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class InPostApiClientResilienceTests
{
    private static readonly ShippingProviderConnectionContext _context = new(
        "c1",
        new Dictionary<string, string> { ["ApiToken"] = "t" },
        Metadata: new Dictionary<string, object>
        {
            [nameof(InPostConnectionSettings)] = new InPostConnectionSettings { OrganizationId = "1" },
        },
        IsTestMode: true);

    private sealed class SequenceHandler(params HttpStatusCode[] statuses) : HttpMessageHandler
    {
        public int Calls { get; private set; }

        protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
        {
            var status = statuses[Math.Min(Calls, statuses.Length - 1)];
            Calls++;
            return Task.FromResult(new HttpResponseMessage(status) { Content = new StringContent("{}") });
        }
    }

    private static InPostShipmentRequest CreateRequest() => new(new InPostParty("A", "B", "a@b.c", "123", new InPostAddress(null, "S", "1", null, null, "C", "00-001", "PL")), [], "inpost_locker_standard", new InPostCustomAttributes("parcel_locker"));

    private static InPostApiClient CreateClient(HttpMessageHandler handler)
    {
        var services = new ServiceCollection();
        services.AddInPostApiClient().ConfigurePrimaryHttpMessageHandler(() => handler);
        return services.BuildServiceProvider().GetRequiredService<InPostApiClient>();
    }

    [Fact]
    public async Task CreateShipmentIsNeverRetried()
    {
        using var handler = new SequenceHandler(HttpStatusCode.ServiceUnavailable, HttpStatusCode.OK);
        var client = CreateClient(handler);

        await Assert.ThrowsAsync<InPostApiException>(() =>
            client.CreateShipmentAsync(_context, CreateRequest(), TestContext.Current.CancellationToken));

        Assert.Equal(1, handler.Calls);
    }

    [Fact]
    public async Task CalculatePricesIsRetriedOnTransientServerError()
    {
        using var handler = new SequenceHandler(HttpStatusCode.ServiceUnavailable, HttpStatusCode.OK);
        var client = CreateClient(handler);

        await client.CalculatePricesAsync(_context, [CreateRequest()], TestContext.Current.CancellationToken);

        Assert.Equal(2, handler.Calls);
    }

    [Fact]
    public async Task ReadCallIsRetriedOnTransientServerError()
    {
        using var handler = new SequenceHandler(HttpStatusCode.ServiceUnavailable, HttpStatusCode.OK);
        var client = CreateClient(handler);

        await client.GetOrganizationAsync(_context, TestContext.Current.CancellationToken);

        Assert.Equal(2, handler.Calls);
    }

    [Fact]
    public async Task ReadCallDoesNotRetryClientError()
    {
        using var handler = new SequenceHandler(HttpStatusCode.Unauthorized);
        var client = CreateClient(handler);

        await Assert.ThrowsAsync<InPostApiException>(() =>
            client.GetOrganizationAsync(_context, TestContext.Current.CancellationToken));

        Assert.Equal(1, handler.Calls);
    }

    [Fact]
    public async Task WriteCallIsNeverRetried()
    {
        using var handler = new SequenceHandler(HttpStatusCode.ServiceUnavailable, HttpStatusCode.OK);
        var client = CreateClient(handler);

        await Assert.ThrowsAsync<InPostApiException>(() =>
            client.CancelShipmentAsync(_context, "1", TestContext.Current.CancellationToken));

        Assert.Equal(1, handler.Calls);
    }

    [Fact]
    public async Task OrganizationIsReadWithAllItsFields()
    {
        using var handler = new RecordingHandler("""
            {
              "href": "https://sandbox-api-shipx-pl.easypack24.net/v1/organizations/7237",
              "id": 7237,
              "owner_id": 7942,
              "name": "Org",
              "tax_id": "PL9930467754",
              "bank_account_number": null,
              "carriers": ["inpost_locker", "inpost_letter"],
              "services": ["inpost_locker_standard", "inpost_courier_c2c"],
              "address": {
                "id": 28680641, "street": "Złocista", "building_number": "34", "line1": null, "line2": null,
                "city": "Zgłobice", "post_code": "33-113", "country_code": "PL"
              },
              "invoice_address": {
                "id": 28680642, "street": "ul. Złocista", "building_number": "34", "line1": null, "line2": null,
                "city": "Zgłobice", "post_code": "33-113", "country_code": "PL"
              },
              "contact_person": {
                "id": 12344, "email": null, "phone": null, "first_name": null, "last_name": null
              },
              "created_at": "2026-11-06T21:09:06.615+01:00",
              "updated_at": "2026-11-06T21:09:06.727+01:00"
            }
            """);
        var client = CreateClient(handler);

        var organization = await client.GetOrganizationAsync(_context, TestContext.Current.CancellationToken);

        Assert.NotNull(organization);
        Assert.Equal(7237, organization.Id);
        Assert.Equal(7942, organization.OwnerId);
        Assert.Equal("Org", organization.Name);
        Assert.Equal("PL9930467754", organization.TaxId);
        Assert.Null(organization.BankAccountNumber);
        Assert.Equal(["inpost_locker", "inpost_letter"], organization.Carriers);
        Assert.Equal(["inpost_locker_standard", "inpost_courier_c2c"], organization.Services);
        Assert.Equal(28680641, organization.Address?.Id);
        Assert.Equal("Złocista", organization.Address?.Street);
        Assert.Equal("34", organization.Address?.BuildingNumber);
        Assert.Null(organization.Address?.Line1);
        Assert.Equal("33-113", organization.Address?.PostCode);
        Assert.Equal("PL", organization.Address?.CountryCode);
        Assert.Equal("ul. Złocista", organization.InvoiceAddress?.Street);
        Assert.Equal(new InPostContactPerson(12344, null, null, null, null), organization.ContactPerson);
        Assert.Equal(new DateTimeOffset(2026, 11, 6, 21, 9, 6, 615, TimeSpan.FromHours(1)), organization.CreatedAt);
        Assert.Equal(new DateTimeOffset(2026, 11, 6, 21, 9, 6, 727, TimeSpan.FromHours(1)), organization.UpdatedAt);
    }

    [Fact]
    public async Task OrganizationJsonIsReturnedUnmodified()
    {
        using var handler = new RecordingHandler("""{"id": 7237, "name": "Org", "unmodeled_field": {"a": null}}""");
        var client = CreateClient(handler);

        var json = await client.GetOrganizationJsonAsync(_context, TestContext.Current.CancellationToken);

        Assert.Equal(7237, json.GetProperty("id").GetInt64());
        Assert.Equal(JsonValueKind.Null, json.GetProperty("unmodeled_field").GetProperty("a").ValueKind);
        Assert.Equal("https://sandbox-api-shipx-pl.easypack24.net/v1/organizations/1", handler.Requests[0].RequestUri?.ToString());
    }

    [Fact]
    public async Task TestModeCallsTheSandboxAndOtherwiseProductionWithTheBearerToken()
    {
        using var handler = new RecordingHandler("{}");
        var client = CreateClient(handler);

        await client.GetOrganizationAsync(_context, TestContext.Current.CancellationToken);
        await client.GetOrganizationAsync(_context with { IsTestMode = false }, TestContext.Current.CancellationToken);

        Assert.Equal("https://sandbox-api-shipx-pl.easypack24.net/v1/organizations/1", handler.Requests[0].RequestUri?.ToString());
        Assert.Equal("https://api-shipx-pl.easypack24.net/v1/organizations/1", handler.Requests[1].RequestUri?.ToString());
        Assert.All(handler.Requests, request =>
        {
            Assert.Equal("Bearer", request.Headers.Authorization?.Scheme);
            Assert.Equal("t", request.Headers.Authorization?.Parameter);
        });
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
