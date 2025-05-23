using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Payment.Abstractions;
using OrchardCore.Commerce.Payment.Przelewy24.Drivers;
using OrchardCore.Commerce.Payment.Przelewy24.Models;
using OrchardCore.Commerce.Payment.Przelewy24.Services;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.Environment.Shell.Configuration;
using OrchardCore.Modules;
using OrchardCore.Navigation;
using OrchardCore.Security.Permissions;
using System;

namespace OrchardCore.Commerce.Payment.Przelewy24;

public class Startup : StartupBase
{
    private readonly IShellConfiguration _shellConfiguration;

    public Startup(IShellConfiguration shellConfiguration) => _shellConfiguration = shellConfiguration;

    public override void ConfigureServices(IServiceCollection services)
    {
        // Payment services
        services.AddScoped<IPaymentProvider, Przelewy24PaymentProvider>();
        services.AddScoped<IPrzelewy24Service, Przelewy24Service>();

        // Configuration, permission, admin things
        services.Configure<Przelewy24Settings>(_shellConfiguration.GetSection("OrchardCoreCommerce_Payment_Przelewy24"));
        services.AddTransient<IConfigureOptions<Przelewy24Settings>, Przelewy24SettingsConfiguration>();
        services.AddSiteDisplayDriver<Przelewy24SettingsDisplayDriver>();
        services.AddScoped<IPermissionProvider, Permissions>();
        services.AddScoped<INavigationProvider, AdminMenu>();

        // API client
        //services.AddTransient<ExactlyApiHandler>();
        //services.AddRefitClient<IExactlyApi>()
        //    .ConfigureHttpClient((provider, client) =>
        //    {
        //        var settings = provider
        //            .GetRequiredService<IHttpContextAccessor>()
        //            .HttpContext!
        //            .RequestServices
        //            .GetRequiredService<IOptionsSnapshot<ExactlySettings>>()
        //            .Value;

        //        client.BaseAddress = new Uri(settings.BaseAddress);
        //    })
        //    .AddHttpMessageHandler<ExactlyApiHandler>();
    }
}
