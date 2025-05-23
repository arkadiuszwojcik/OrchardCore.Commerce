using OrchardCore.Commerce.Abstractions.Abstractions;
using OrchardCore.Commerce.Payment.Abstractions;
using OrchardCore.Commerce.Payment.ViewModels;
using OrchardCore.ContentManagement;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24PaymentProvider : IPaymentProvider
{
    public const string ProviderName = "Przelewy24";

    public string Name => throw new NotImplementedException();

    public Task<object> CreatePaymentProviderDataAsync(IPaymentViewModel model, bool isPaymentRequest = false, string shoppingCartId = null) => throw new NotImplementedException();
    public Task<PaymentOperationStatusViewModel> UpdateAndRedirectToFinishedOrderAsync(ContentItem order, string shoppingCartId) => throw new NotImplementedException();
}
