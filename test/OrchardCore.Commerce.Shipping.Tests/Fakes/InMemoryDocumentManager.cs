using OrchardCore.Data.Documents;
using OrchardCore.Documents;
using System;
using System.Text.Json;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Tests.Fakes;

/// <summary>
/// An in-memory <see cref="IDocumentManager{TDocument}"/> that behaves like the real one where it matters: the
/// immutable document is the shared cached instance, the mutable one is a separate copy, and a document is only
/// visible to readers after <see cref="UpdateAsync"/>.
/// </summary>
public class InMemoryDocumentManager<TDocument> : IDocumentManager<TDocument>
    where TDocument : class, IDocument, new()
{
    private TDocument? _stored;

    public Task<TDocument> GetOrCreateMutableAsync(Func<Task<TDocument>>? factoryAsync = null) =>
        Task.FromResult(_stored is null ? new TDocument() : Copy(_stored));

    public Task<TDocument> GetOrCreateImmutableAsync(Func<Task<TDocument>>? factoryAsync = null) =>
        Task.FromResult(_stored ??= new TDocument());

    public async Task UpdateAsync(TDocument document, Func<TDocument, Task>? afterUpdateAsync = null)
    {
        _stored = Copy(document);

        if (afterUpdateAsync is not null) await afterUpdateAsync(document);
    }

    private static TDocument Copy(TDocument document) =>
        JsonSerializer.Deserialize<TDocument>(JsonSerializer.Serialize(document))!;
}
