using OrchardCore.Commerce.Abstractions.Models;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

public interface IOrderShippingCostApplier
{
    Task ApplyAsync(OrderPart orderPart, string? shoppingCartId, CancellationToken cancellationToken = default);
}