using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Payment.Przelewy24.Settings;
using OrchardCore.Settings;
using static Dapper.SqlMapper;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24SettingsConfiguration : IConfigureOptions<Przelewy24Settings>
{
    private readonly ISiteService _siteService;

    public Przelewy24SettingsConfiguration(ISiteService siteService) => _siteService = siteService;

    public void Configure(Przelewy24Settings options)
    {
        var settings = _siteService
            .GetSiteSettingsAsync()
            .GetAwaiter()
            .GetResult()
            .As<Przelewy24Settings>();

        if (settings != null)
        {
            options.MerchantId = settings.MerchantId;
            options.PosId = settings.PosId;
            options.ApiKey = settings.ApiKey;
            options.CrcKey = settings.CrcKey;
        }
    }
}
