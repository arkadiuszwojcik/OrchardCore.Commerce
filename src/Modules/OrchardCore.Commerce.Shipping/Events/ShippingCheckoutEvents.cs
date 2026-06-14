using OrchardCore.Commerce.Abstractions.Abstractions;
using OrchardCore.Commerce.Abstractions.Models;
using OrchardCore.Commerce.Abstractions.ViewModels;
using OrchardCore.Commerce.Shipping.Abstractions;
using System.Collections.Generic;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Events;

public class ShippingCheckoutEvents : ICheckoutEvents
{
    private readonly IOrderShippingCostApplier _orderShippingCostApplier;

    public ShippingCheckoutEvents(IOrderShippingCostApplier orderShippingCostApplier) =>
        _orderShippingCostApplier = orderShippingCostApplier;

    public Task OrderCreatingAsync(OrderPart orderPart, string shoppingCartId) =>
        _orderShippingCostApplier.ApplyAsync(orderPart, shoppingCartId);

    public Task ViewModelCreatedAsync(IList<ShoppingCartLineViewModel> lines, ICheckoutViewModel checkoutViewModel) =>
        Task.CompletedTask;
}