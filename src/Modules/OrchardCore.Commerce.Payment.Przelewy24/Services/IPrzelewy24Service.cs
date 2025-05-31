using OrchardCore.Commerce.Abstractions.Models;
using OrchardCore.Commerce.MoneyDataType;
using OrchardCore.Commerce.Payment.Przelewy24.Models;
using OrchardCore.ContentManagement;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public interface IPrzelewy24Service
{
    Task<bool> TestAccess(CancellationToken cancellationToken = default);

    /// <summary>
    /// Creates a new transaction for the current user based on the provided order.
    /// </summary>
    /// <param name="orderPart">
    /// The part whose <see cref="ContentItem.ContentItemId"/> and <see cref="OrderPart.LineItems"/>
    /// are used in the request.
    /// </param>
    /// <param name="total">
    /// The charge total to be sent in the request. If <see langword="null"/>, it's calculated using
    /// the total from the current shopping cart with all checkout events applied.
    /// </param>
    Task<TransactionRegisterResponse> CreateTransactionAsync(OrderPart orderPart, Amount? total = null, CancellationToken cancellationToken = default);
}
