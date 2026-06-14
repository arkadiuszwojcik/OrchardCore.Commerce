using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Options;
using OrchardCore.Commerce.Shipping.Models;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Services;

public class InPostShippingProvider : IShippingRateProvider
{
    private readonly IInPostShippingClient _shippingClient;
    private readonly IOptions<InPostShippingOptions> _options;

    public InPostShippingProvider(IInPostShippingClient shippingClient, IOptions<InPostShippingOptions> options)
    {
        _shippingClient = shippingClient;
        _options = options;
    }

    public int Order => 100;

    public Task<bool> IsApplicableAsync(ShippingRateRequest request, CancellationToken cancellationToken = default)
    {
        var enabled = _options.Value.Enabled;
        return Task.FromResult(enabled && request.ShippingAddress is not null);
    }

    public Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
        ShippingRateRequest request,
        CancellationToken cancellationToken = default) =>
        _shippingClient.GetRatesAsync(request, cancellationToken);
}