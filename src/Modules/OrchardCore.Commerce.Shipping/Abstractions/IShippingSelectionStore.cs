using OrchardCore.Commerce.Shipping.Models;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

public interface IShippingSelectionStore
{
    Task<ShippingSelection?> GetAsync(string shoppingCartId, CancellationToken cancellationToken = default);

    Task SetAsync(string shoppingCartId, ShippingSelection selection, CancellationToken cancellationToken = default);

    Task RemoveAsync(string shoppingCartId, CancellationToken cancellationToken = default);
}