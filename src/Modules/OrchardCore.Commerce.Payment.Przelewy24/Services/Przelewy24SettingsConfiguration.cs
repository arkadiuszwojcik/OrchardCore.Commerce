using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Payment.Przelewy24.Models;
using OrchardCore.Settings;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24SettingsConfiguration : IConfigureOptions<Przelewy24Settings>
{
    private readonly ISiteService _siteService;

    public Przelewy24SettingsConfiguration(ISiteService siteService) => _siteService = siteService;

    public void Configure(Przelewy24Settings options)
    {
        var siteSettings = _siteService
            .GetSiteSettingsAsync()
            .GetAwaiter()
            .GetResult()
            .As<Przelewy24Settings>();

        siteSettings.CopyTo(options);
    }
}
