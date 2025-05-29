using Lombiq.HelpfulLibraries.AspNetCore.Exceptions;
using Microsoft.AspNetCore.Http;
using OrchardCore.Commerce.Payment.Przelewy24.Constants;
using OrchardCore.Commerce.Payment.Przelewy24.Models;
using Refit;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24Service : IPrzelewy24Service
{
    private readonly IPrzelewy24Api _api;
    private readonly IHttpContextAccessor _hca;

    public Przelewy24Service(IPrzelewy24Api api, IHttpContextAccessor hca)
    {
        _api = api;
        _hca = hca;
    }

    public async Task<bool> TestAccess(CancellationToken cancellationToken)
    {
        using var result = await _api.TestAccessAsync(cancellationToken);
        return EvaluateResult(result);
    }

    private static bool EvaluateResult(IApiResponse<Przelewy24TestAccessResponse> result)
    {
        // If the request is not successful, try to parse the response error and throw a more specific FrontendException
        // instead of the ApiException.
        if (result.Error?.Content is { } error && error.StartsWith('{'))
        {
            try
            {
                var content = JsonSerializer.Deserialize<Przelewy24TestAccessResponse>(error, Przelewy24Constants.JsonSerializerOptions);
                content.ThrowIfHasErrors();
            }
            catch (FrontendException)
            {
                throw;
            }
            catch
            {
                throw result.Error;
            }
        }

        // Handle any other non-specific ApiExceptions.
        if (result.Error is { } apiException) throw apiException;

        // In the unlikely case that the HTTP response is success but there was still an error somehow.
        result.Content!.ThrowIfHasErrors();

        return result.Content.Data;
    }

    private static T EvaluateResult<T>(IApiResponse<Przelewy24Response<T>> result)
    {
        // If the request is not successful, try to parse the response error and throw a more specific FrontendException
        // instead of the ApiException.
        if (result.Error?.Content is { } error && error.StartsWith('{'))
        {
            try
            {
                var content = JsonSerializer.Deserialize<Przelewy24Response<T>>(error, Przelewy24Constants.JsonSerializerOptions);
                content.ThrowIfHasErrors();
            }
            catch (FrontendException)
            {
                throw;
            }
            catch
            {
                throw result.Error;
            }
        }

        // Handle any other non-specific ApiExceptions.
        if (result.Error is { } apiException) throw apiException;

        // In the unlikely case that the HTTP response is success but there was still an error somehow.
        result.Content!.ThrowIfHasErrors();

        return result.Content.Data!;
    }
}
