using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

public class ShippingService : IShippingService
{
    private readonly IEnumerable<IShippingRateProvider> _rateProviders;
    private readonly IShippingSelectionStore _shippingSelectionStore;
    private readonly ILogger<ShippingService> _logger;

    public ShippingService(
        IEnumerable<IShippingRateProvider> rateProviders,
        IShippingSelectionStore shippingSelectionStore,
        ILogger<ShippingService> logger)
    {
        _rateProviders = rateProviders;
        _shippingSelectionStore = shippingSelectionStore;
        _logger = logger;
    }

    public async Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
        ShippingRateRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);

        if (string.IsNullOrWhiteSpace(request.Currency)) return [];

        var rates = new List<ShippingRateOption>();

        foreach (var provider in _rateProviders.OrderBy(provider => provider.Order))
        {
            if (!await provider.IsApplicableAsync(request, cancellationToken)) continue;

            var providerRates = await provider.GetRatesAsync(request, cancellationToken);
            if (providerRates.Count == 0) continue;

            rates.AddRange(providerRates.Where(rate =>
                !string.IsNullOrWhiteSpace(rate.Provider) &&
                !string.IsNullOrWhiteSpace(rate.MethodCode) &&
                !string.IsNullOrWhiteSpace(rate.Currency)));
        }

        return rates;
    }

    public async Task<ShippingRateSelectionResult> SelectRateAsync(
        ShippingRateSelectionRequest request,
        CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(request);

        if (string.IsNullOrWhiteSpace(request.ShoppingCartId))
        {
            return new ShippingRateSelectionResult(false, "ShoppingCartId is required.");
        }

        if (request.Price < 0)
        {
            return new ShippingRateSelectionResult(false, "Shipping price can't be negative.");
        }

        if (string.IsNullOrWhiteSpace(request.Provider) ||
            string.IsNullOrWhiteSpace(request.MethodCode) ||
            string.IsNullOrWhiteSpace(request.Currency))
        {
            return new ShippingRateSelectionResult(false, "Provider, MethodCode, and Currency are required.");
        }

        var selection = new ShippingSelection(
            Provider: request.Provider,
            MethodCode: request.MethodCode,
            Price: request.Price,
            Currency: request.Currency,
            DisplayName: request.DisplayName,
            ExternalMethodCode: request.ExternalMethodCode);

        await _shippingSelectionStore.SetAsync(request.ShoppingCartId, selection, cancellationToken);

        _logger.LogInformation(
            "Shipping method selected for cart {ShoppingCartId}: {Provider}/{MethodCode} at {Price} {Currency}.",
            request.ShoppingCartId,
            request.Provider,
            request.MethodCode,
            request.Price,
            request.Currency);

        return new ShippingRateSelectionResult(true);
    }

    public Task<ShippingSelection?> GetSelectionAsync(
        string shoppingCartId,
        CancellationToken cancellationToken = default) =>
        _shippingSelectionStore.GetAsync(shoppingCartId, cancellationToken);
}