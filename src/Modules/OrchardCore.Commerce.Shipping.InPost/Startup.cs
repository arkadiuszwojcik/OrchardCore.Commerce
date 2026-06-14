using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Drivers;
using OrchardCore.Commerce.Shipping.InPost.Navigation;
using OrchardCore.Commerce.Shipping.InPost.Options;
using OrchardCore.Commerce.Shipping.InPost.Permissions;
using OrchardCore.Commerce.Shipping.InPost.Services;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.Modules;
using OrchardCore.Navigation;
using OrchardCore.Security.Permissions;

namespace OrchardCore.Commerce.Shipping.InPost;

public class Startup : StartupBase
{
    private readonly IConfiguration _configuration;

    public Startup(IConfiguration configuration) => _configuration = configuration;

    public override void ConfigureServices(IServiceCollection services)
    {
        services
            .AddOptions<InPostShippingOptions>()
            .Bind(_configuration.GetSection(InPostShippingOptions.SectionName))
            .ValidateDataAnnotations()
            .ValidateOnStart();

        services.AddTransient<IConfigureOptions<InPostShippingOptions>, InPostShippingOptionsConfiguration>();
        services.AddSingleton<IValidateOptions<InPostShippingOptions>, InPostShippingOptionsValidator>();
        services.AddSiteDisplayDriver<InPostShippingOptionsDisplayDriver>();
        services.AddScoped<IPermissionProvider, InPostShippingPermissions>();
        services.AddScoped<INavigationProvider, AdminMenu>();

        services.AddHttpClient();
        services.AddScoped<IInPostShippingClient, InPostShippingClient>();
        services.AddScoped<IShippingRateProvider, InPostShippingProvider>();
    }
}