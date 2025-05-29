using OrchardCore.Commerce.Payment.Przelewy24.Models;
using Refit;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public interface IPrzelewy24Api
{
    [Get("/api/v1/testAccess")]
    Task<ApiResponse<Przelewy24TestAccessResponse>> TestAccessAsync(CancellationToken cancellationToken);

    [Post("/api/v1/transaction/register")]
    Task<ApiResponse<Przelewy24Response<TransactionRegisterResponse>>> CreateTransactionAsync(
        [Body(buffered: true)] TransactionRegisterRequest data, CancellationToken cancellationToken);
}
