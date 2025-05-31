using Lombiq.HelpfulLibraries.OrchardCore.DependencyInjection;
using OrchardCore.Commerce.Abstractions.Abstractions;
using OrchardCore.Commerce.Payment.Abstractions;
using OrchardCore.Commerce.Payment.Przelewy24.Settings;
using OrchardCore.Commerce.Payment.ViewModels;
using OrchardCore.ContentManagement;
using OrchardCore.Settings;
using System;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24PaymentProvider : IPaymentProvider
{
    public const string ProviderName = "Przelewy24";

    private readonly ISiteService _siteService;

    public string Name => ProviderName;

    public Przelewy24PaymentProvider(IOrchardServices<Przelewy24PaymentProvider> services)
    {
        _siteService = services.SiteService.Value;
    }

    public async Task<object> CreatePaymentProviderDataAsync(IPaymentViewModel model, bool isPaymentRequest = false, string shoppingCartId = null)
    {
        var settings = (await _siteService.GetSiteSettingsAsync())?.As<Przelewy24Settings>();
        return string.IsNullOrEmpty(settings?.ApiKey) || string.IsNullOrEmpty(settings.CrcKey) ? null : new object();
    }

    public Task<PaymentOperationStatusViewModel> UpdateAndRedirectToFinishedOrderAsync(ContentItem order, string shoppingCartId)
    {
        return null;
    }
}
