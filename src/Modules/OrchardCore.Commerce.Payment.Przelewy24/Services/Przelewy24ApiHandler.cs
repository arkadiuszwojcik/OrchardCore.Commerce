using Microsoft.AspNetCore.DataProtection;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Payment.Przelewy24.Extensions;
using OrchardCore.Commerce.Payment.Przelewy24.Settings;
using System;
using System.Net.Http;
using System.Text;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24ApiHandler : DelegatingHandler
{
    private readonly string _accessToken;

    public Przelewy24ApiHandler(
        IOptionsSnapshot<Przelewy24Settings> settings,
        IDataProtectionProvider dataProtectionProvider,
        ILogger<Przelewy24ApiHandler> logger)
    {
        var p24Settings = settings.Value;
        var apiKey = p24Settings.ApiKey.DecryptSecretString(dataProtectionProvider, logger);
        _accessToken = Convert.ToBase64String(Encoding.UTF8.GetBytes($"{p24Settings.MerchantId}:{apiKey}"));
    }

    protected override Task<HttpResponseMessage> SendAsync(HttpRequestMessage request, CancellationToken cancellationToken)
    {
        request.Headers.Add("Authorization", "Basic " + _accessToken);

        return base.SendAsync(request, cancellationToken);
    }
}
