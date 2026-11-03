using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client;
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

    [Fact]
    public async Task ReadCallIsRetriedOnTransientServerError()
    {
        var handler = new SequenceHandler(HttpStatusCode.ServiceUnavailable, HttpStatusCode.OK);
        var client = new InPostApiClient(new HttpClient(handler));

        await client.GetOrganizationAsync(_context, TestContext.Current.CancellationToken);

        Assert.Equal(2, handler.Calls);
    }

    [Fact]
    public async Task ReadCallDoesNotRetryClientError()
    {
        var handler = new SequenceHandler(HttpStatusCode.Unauthorized);
        var client = new InPostApiClient(new HttpClient(handler));

        await Assert.ThrowsAsync<InPostApiException>(() =>
            client.GetOrganizationAsync(_context, TestContext.Current.CancellationToken));

        Assert.Equal(1, handler.Calls);
    }

    [Fact]
    public async Task WriteCallIsNeverRetried()
    {
        var handler = new SequenceHandler(HttpStatusCode.ServiceUnavailable, HttpStatusCode.OK);
        var client = new InPostApiClient(new HttpClient(handler));

        await Assert.ThrowsAsync<InPostApiException>(() =>
            client.CancelShipmentAsync(_context, "1", TestContext.Current.CancellationToken));

        Assert.Equal(1, handler.Calls);
    }
}
