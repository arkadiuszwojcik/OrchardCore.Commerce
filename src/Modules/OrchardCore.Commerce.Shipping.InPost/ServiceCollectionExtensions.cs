using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Http.Resilience;
using OrchardCore.Commerce.Shipping.InPost.Client;
using Polly;
using Polly.Timeout;
using Refit;
using System;
using System.Net;
using System.Net.Http;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost;

public static class ServiceCollectionExtensions
{
    private const int MaxReadRetries = 2;

    /// <summary>
    /// Registers <see cref="InPostApiClient"/> on top of the Refit-based <see cref="IInPostApi"/>. Retries are limited
    /// to side-effect-free calls: POST /shipments and DELETE are never retried because a lost response may hide a
    /// created shipment, which the core handles as an uncertain result.
    /// </summary>
    public static IHttpClientBuilder AddInPostApiClient(this IServiceCollection services)
    {
        services.AddTransient<InPostConnectionHandler>();
        services.AddTransient<InPostApiClient>();

        var builder = services
            .AddRefitClient<IInPostApi>(new RefitSettings
            {
                ContentSerializer = new SystemTextJsonContentSerializer(InPostJson.Options),
            })
            .ConfigureHttpClient(client =>
            {
                // The real base URL (production or sandbox) is chosen per request by InPostConnectionHandler.
                client.BaseAddress = new Uri("https://api-shipx-pl.easypack24.net");
                client.Timeout = TimeSpan.FromSeconds(60);
            });

        // Handler order matters: the retry pipeline must wrap the connection handler to see its exceptions.
        builder.AddResilienceHandler("InPostReadRetry", pipeline =>
        {
            pipeline.AddRetry(new HttpRetryStrategyOptions
            {
                MaxRetryAttempts = MaxReadRetries,
                BackoffType = DelayBackoffType.Exponential,
                UseJitter = true,
                Delay = TimeSpan.FromMilliseconds(250),
                ShouldHandle = args => ValueTask.FromResult(
                    IsRetryableRequest(args.Context.GetRequestMessage()) && IsTransient(args.Outcome.Exception)),
            });
            pipeline.AddTimeout(TimeSpan.FromSeconds(15));
        });
        builder.AddHttpMessageHandler<InPostConnectionHandler>();

        return builder;
    }

    private static bool IsRetryableRequest(HttpRequestMessage? request) =>
        request is not null &&
        (request.Method == HttpMethod.Get ||
        request.RequestUri?.AbsolutePath.EndsWith("/shipments/calculate", StringComparison.Ordinal) == true);

    private static bool IsTransient(Exception? exception) =>
        exception switch
        {
            InPostApiException api => api.StatusCode is HttpStatusCode.TooManyRequests or >= HttpStatusCode.InternalServerError,
            HttpRequestException => true,
            TimeoutRejectedException => true,
            _ => false,
        };
}
