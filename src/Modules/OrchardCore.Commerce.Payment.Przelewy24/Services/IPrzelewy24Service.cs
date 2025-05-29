using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Payment.Przelewy24.Services;

public interface IPrzelewy24Service
{
    Task<bool> TestAccess(CancellationToken cancellationToken = default);
}
