using OrchardCore.Commerce.Abstractions.Abstractions;
using OrchardCore.Commerce.Abstractions.Models;
using OrchardCore.Commerce.Abstractions.ViewModels;
using OrchardCore.ContentManagement;
using OrchardCore.Commerce.Shipping.Abstractions;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Events;

public class ShippingOrderEvents : IOrderEvents
{
    private readonly IOrderShippingCostApplier _orderShippingCostApplier;

    public ShippingOrderEvents(IOrderShippingCostApplier orderShippingCostApplier) =>
        _orderShippingCostApplier = orderShippingCostApplier;

    public Task CreatedFreeAsync(OrderPart orderPart, ShoppingCart cart, ShoppingCartViewModel viewModel) =>
        _orderShippingCostApplier.ApplyAsync(orderPart, cart.Id);

    public Task OrderedAsync(ContentItem order, string shoppingCartId) => Task.CompletedTask;

    public Task FinalizeAsync(ContentItem order, string shoppingCartId, string paymentProviderName) => Task.CompletedTask;
}