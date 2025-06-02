using Lombiq.HelpfulLibraries.AspNetCore.Exceptions;
using Microsoft.AspNetCore.Http;
using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Abstractions.Models;
using OrchardCore.Commerce.MoneyDataType;
using OrchardCore.Commerce.Payment.Abstractions;
using OrchardCore.Commerce.Payment.Przelewy24.Constants;
using OrchardCore.Commerce.Payment.Przelewy24.Models;
using OrchardCore.Commerce.Payment.Przelewy24.Helpers;
using Refit;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using OrchardCore.Settings;
using OrchardCore.Commerce.Payment.Przelewy24.Settings;
using System.Collections.Generic;
using OrchardCore.Commerce.Payment.Przelewy24.Extensions;
using Microsoft.AspNetCore.DataProtection;
using Microsoft.Extensions.Logging;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24Service : IPrzelewy24Service
{
    private readonly IPrzelewy24Api _api;
    private readonly IHttpContextAccessor _hca;
    private readonly ISiteService _siteService;
    private readonly IDataProtectionProvider _dataProtectionProvider;
    private readonly ILogger _logger;

    public Przelewy24Service(IPrzelewy24Api api, IHttpContextAccessor hca, ISiteService siteService, IDataProtectionProvider dataProtectionProvider, ILogger<Przelewy24Service> logger)
    {
        _api = api;
        _hca = hca;
        _siteService = siteService;
        _dataProtectionProvider = dataProtectionProvider;
        _logger = logger;
    }

    public async Task<bool> TestAccess(CancellationToken cancellationToken)
    {
        using var result = await _api.TestAccessAsync(cancellationToken);
        return EvaluateResult(result);
    }

    public async Task<TransactionRegisterResponse> CreateTransactionAsync(OrderPart orderPart, Amount? total = null, CancellationToken cancellationToken = default)
    {
        var data = await GetTransactionRegisterRequest(orderPart, _hca.HttpContext, total);
        using var result = await _api.RegisterTransactionAsync(null /*TODO*/, cancellationToken);
        return EvaluateResult(result);
    }

    private async Task<TransactionRegisterRequest> GetTransactionRegisterRequest(OrderPart orderPart, HttpContext context, Amount? total = null)
    {
        var apiSettings = (await _siteService.GetSiteSettingsAsync()).As<Przelewy24Settings>();
        var crc = apiSettings.CrcKey.DecryptSecretString(_dataProtectionProvider, _logger);

        var provider = context.RequestServices;
        var amount = total ?? await provider.GetRequiredService<IPaymentService>().GetTotalAsync(shoppingCartId: null);
        var amountInLowesttUnit = (int)AmountHelpers.GetPaymentAmount(amount);

        var signList = new List<KeyValuePair<string, object>>() {
            new("sessionId", orderPart.ContentItem.ContentItemId),
            new("merchantId", apiSettings.MerchantId),
            new("amount", amountInLowesttUnit),
            new("currency", amount.Currency.CurrencyIsoCode),
            new("crc", crc)
        };

        return new TransactionRegisterRequest
        {
            MerchantId = apiSettings.MerchantId,
            PosId = apiSettings.PosId ?? apiSettings.MerchantId,
            SessionId = orderPart.ContentItem.ContentItemId,
            Amount = amountInLowesttUnit,
            Currency = amount.Currency.CurrencyIsoCode,
            Description = "",
            Email = orderPart.Email.Text,
            Country = "PL",
            Language = "pl",
            UrlReturn = "",
            Sign = Przelewy24Crypto.CalculateSign(signList, crc)
        };
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
