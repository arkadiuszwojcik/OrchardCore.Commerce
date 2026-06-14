using OrchardCore.Commerce.Abstractions.Models;
using OrchardCore.Commerce.MoneyDataType;
using OrchardCore.Commerce.MoneyDataType.Abstractions;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Constants;
using System;
using System.Linq;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

public class OrderShippingCostApplier : IOrderShippingCostApplier
{
    private readonly IShippingService _shippingService;
    private readonly ICurrencyProvider _currencyProvider;

    public OrderShippingCostApplier(IShippingService shippingService, ICurrencyProvider currencyProvider)
    {
        _shippingService = shippingService;
        _currencyProvider = currencyProvider;
    }

    public async Task ApplyAsync(OrderPart orderPart, string? shoppingCartId, CancellationToken cancellationToken = default)
    {
        ArgumentNullException.ThrowIfNull(orderPart);
        if (string.IsNullOrWhiteSpace(shoppingCartId)) return;

        var selection = await _shippingService.GetSelectionAsync(shoppingCartId, cancellationToken);
        if (selection is null) return;

        if (!_currencyProvider.IsKnownCurrency(selection.Currency)) return;

        var existingShippingCosts = orderPart.AdditionalCosts
            .Where(cost => string.Equals(cost.Kind, AdditionalCostKinds.Shipping, StringComparison.OrdinalIgnoreCase))
            .ToList();

        foreach (var shippingCost in existingShippingCosts)
        {
            orderPart.AdditionalCosts.Remove(shippingCost);
        }

        var description = string.IsNullOrWhiteSpace(selection.DisplayName)
            ? $"{selection.Provider} / {selection.MethodCode}"
            : selection.DisplayName;

        orderPart.AdditionalCosts.Add(new OrderAdditionalCost
        {
            Kind = AdditionalCostKinds.Shipping,
            Description = description,
            Cost = new Amount(selection.Price, _currencyProvider.GetCurrency(selection.Currency)),
        });
    }
}