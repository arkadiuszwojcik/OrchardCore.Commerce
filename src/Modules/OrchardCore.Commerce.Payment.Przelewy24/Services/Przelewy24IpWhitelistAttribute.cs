using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Filters;
using System.Linq;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public class Przelewy24IpWhitelistAttribute : ActionFilterAttribute
{
    private readonly string[] _allowedIps;

    public Przelewy24IpWhitelistAttribute(params string[] allowedIps)
    {
        _allowedIps = allowedIps;
    }

    public override void OnActionExecuting(ActionExecutingContext context)
    {
        var ip = context.HttpContext.Connection.RemoteIpAddress?.ToString();

        // Optional: handle forwarded headers if behind proxy
        if (context.HttpContext.Request.Headers.TryGetValue("X-Forwarded-For", out var forwardedFor))
        {
            ip = forwardedFor.FirstOrDefault()?.Split(',').FirstOrDefault()?.Trim() ?? ip;
        }

        if (!_allowedIps.Contains(ip))
        {
            context.Result = new ContentResult
            {
                StatusCode = 403,
                Content = "Access denied."
            };
        }
    }
}
