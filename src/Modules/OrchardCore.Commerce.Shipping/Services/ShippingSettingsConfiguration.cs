using Microsoft.Extensions.Options;
using OrchardCore.Settings;

namespace OrchardCore.Commerce.Shipping.Services;

public class ShippingSettingsConfiguration : IConfigureOptions<ShippingOptions>
{
    private readonly ISiteService _siteService;

    public ShippingSettingsConfiguration(ISiteService siteService) => _siteService = siteService;

    public void Configure(ShippingOptions options) =>
        _siteService
            .GetSiteSettings()
            .GetOrCreate<ShippingOptions>()
            .CopyTo(options);
}
