using Refit;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public interface IPrzelewy24Api
{
    [Post("/api/v1/transaction/register")]
    Task<ApiResponse<ExactlyResponse<ChargeResponse>>> CreateTransactionAsync(
        [Body(buffered: true)] ExactlyDataWrapper<ExactlyRequest<ChargeRequest>> data);
}
