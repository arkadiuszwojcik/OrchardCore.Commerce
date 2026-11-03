using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Commerce.Shipping.InPost.Client.Models;
using System;
using System.Collections.Generic;
using System.Net;
using System.Net.Http;
using System.Threading;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class InPostApiClientResilienceTests
{
    private static readonly ShippingProviderConnectionContext _context = new(
        "c1",
        new Dictionary<string, string> { ["ApiToken"] = "t", ["OrganizationId"] = "1" },
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

    private static InPostShipmentRequest CreateRequest() => new(new InPostContact("A", "B", "a@b.c", "123", new InPostAddress("S", "1", "C", "00-001", "PL")), [], "inpost_locker_standard", new InPostCustomAttributes("parcel_locker"));

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
}
