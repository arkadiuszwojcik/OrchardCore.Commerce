using Lombiq.HelpfulLibraries.OrchardCore.DependencyInjection;
using Lombiq.HelpfulLibraries.OrchardCore.Validation;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Localization;
using Microsoft.Extensions.Localization;
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Abstractions.Models;
using OrchardCore.Commerce.Payment.Controllers;
using OrchardCore.Commerce.Payment.Przelewy24.Drivers;
using OrchardCore.Commerce.Payment.Przelewy24.Services;
using OrchardCore.DisplayManagement.Notify;
using OrchardCore.Mvc.Core.Utilities;
using Org.BouncyCastle.Asn1.X509;
using Refit;
using System;
using System.Threading;
using System.Threading.Tasks;

using AdminController = OrchardCore.Settings.Controllers.AdminController;
using FrontendException = Lombiq.HelpfulLibraries.AspNetCore.Exceptions.FrontendException;

namespace OrchardCore.Commerce.Payment.Przelewy24.Controllers;

public class Przelewy24Controller : PaymentBaseController
{
    private readonly IPrzelewy24Service _przelewy24Service;
    private readonly ILogger<Przelewy24Controller> _logger;
    private readonly INotifier _notifier;
    private readonly IHtmlLocalizer<Przelewy24Controller> H;
    private readonly IStringLocalizer<Przelewy24Controller> S;

    public Przelewy24Controller(
        IPrzelewy24Service przelewy24Service,
        IOrchardServices<Przelewy24Controller> services,
        INotifier notifier)
        : base(notifier, services.Logger.Value)
    {
        _przelewy24Service = przelewy24Service;
        _logger = services.Logger.Value;
        _notifier = notifier;
        H = services.HtmlLocalizer.Value;
        S = services.StringLocalizer.Value;
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> CreateTransaction(string shoppingCartId)
    {
        return null;
    }

    public async Task<IActionResult> GetRedirectUrl(string transactionId)
    {
        return null;
    }

    public async Task<IActionResult> VerifyApi()
    {
        try
        {
            var result = await _przelewy24Service.TestAccess();
            await _notifier.SuccessAsync(H["The Przelewy24 API access works correctly."]);
        }
        catch (ApiException exception)
        {
            _logger.LogError(exception, "An API error was encountered.");
            await _notifier.ErrorAsync(H["An API error was encountered: {0}", exception.Message]);
        }
        catch (FrontendException exception)
        {
            _logger.LogError(exception, "A front-end readable error was encountered.");
            await _notifier.FrontEndErrorAsync(exception);
        }
        catch (Exception exception)
        {
            _logger.LogError(exception, "An unknown error was encountered.");

            var error = exception.ToString();
            var html = $"{H["An unknown error was encountered:"].Html()}<br>{error.Replace("\n", "<br>")}";
            await _notifier.ErrorAsync(new LocalizedHtmlString(html, html));
        }

        return RedirectToAction(
            nameof(AdminController.Index),
            typeof(AdminController).ControllerName(),
            new { area = "OrchardCore.Settings", groupId = Przelewy24SettingsDisplayDriver.EditorGroupId });
    }
}
