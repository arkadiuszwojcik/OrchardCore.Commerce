using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Storage for shipping documents (labels, customs forms, manifests).
/// </summary>
public interface IShippingDocumentStore
{
    /// <summary>
    /// Stores a shipping document (label, customs form, etc.).
    /// </summary>
    Task<string> StoreDocumentAsync(
        string shipmentId,
        string documentType,
        byte[] documentData,
        string? fileName = null,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Retrieves a stored document.
    /// </summary>
    Task<byte[]?> GetDocumentAsync(
        string documentId,
        CancellationToken cancellationToken = default);

    /// <summary>
    /// Deletes a document.
    /// </summary>
    Task DeleteDocumentAsync(
        string documentId,
        CancellationToken cancellationToken = default);
}
