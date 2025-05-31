using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.DataProtection;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Payment.Przelewy24.Models;
using OrchardCore.Commerce.Payment.Przelewy24.Services;
using OrchardCore.Commerce.Payment.Przelewy24.Settings;
using OrchardCore.Settings;
using System;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Controllers;

[Route("przelewy24-webhook")]
[ApiController]
[Authorize(AuthenticationSchemes = "Api"), IgnoreAntiforgeryToken, AllowAnonymous]
[Przelewy24IpWhitelist("5.252.202.255", "5.252.202.254", "20.215.81.124")]
public class WebhookController : ControllerBase
{
    private readonly ISiteService _siteService;
    private readonly IDataProtectionProvider _dataProtectionProvider;
    private readonly ILogger<WebhookController> _logger;

    public WebhookController(
        ISiteService siteService,
        IDataProtectionProvider dataProtectionProvider,
        ILogger<WebhookController> logger)
    {
        _siteService = siteService;
        _dataProtectionProvider = dataProtectionProvider;
        _logger = logger;
    }

    [HttpPost]
    public async Task<IActionResult> Index()
    {
        try
        {
            var notification = await HttpContext.Request.ReadFromJsonAsync<TransactionNotification>(HttpContext.RequestAborted);

            var przelewy24ApiSettings = (await _siteService.GetSiteSettingsAsync()).As<Przelewy24Settings>();
            //var przelewy24CrcKey = przelewy24ApiSettings.DecryptCrcKey(_dataProtectionProvider, _logger);

            //if (notification.VerifySign(przelewy24CrcKey) == false)
            //{
            //    return BadRequest();
            //}

            // TODO: confirm payment

            return Ok();
        }
        catch (Exception e)
        {
            return BadRequest(e);
        }
    }
}
