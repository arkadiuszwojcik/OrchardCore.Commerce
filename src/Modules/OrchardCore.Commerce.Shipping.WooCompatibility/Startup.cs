using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Routing;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Extensions;
using OrchardCore.Commerce.Shipping.WooCompatibility.Options;
using OrchardCore.Modules;
using System;

namespace OrchardCore.Commerce.Shipping.WooCompatibility;

public class Startup : StartupBase
{
    private readonly IConfiguration _configuration;

    public Startup(IConfiguration configuration) => _configuration = configuration;

    public override void ConfigureServices(IServiceCollection services) =>
        services.Configure<WooShippingCompatibilityApiOptions>(
            _configuration.GetSection(WooShippingCompatibilityApiOptions.SectionName));

    public override void Configure(IApplicationBuilder app, IEndpointRouteBuilder routes, IServiceProvider serviceProvider) =>
        routes.AddWooShippingCompatibilityEndpoints();
}