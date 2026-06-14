using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.InPost.Options;
using OrchardCore.Settings;

namespace OrchardCore.Commerce.Shipping.InPost.Services;

public class InPostShippingOptionsConfiguration : IConfigureOptions<InPostShippingOptions>
{
    private readonly ISiteService _siteService;

    public InPostShippingOptionsConfiguration(ISiteService siteService) => _siteService = siteService;

    public void Configure(InPostShippingOptions options)
    {
        var siteSettings = _siteService
            .GetSiteSettings()
            .As<InPostShippingOptions>();

        siteSettings.CopyTo(options);
    }
}