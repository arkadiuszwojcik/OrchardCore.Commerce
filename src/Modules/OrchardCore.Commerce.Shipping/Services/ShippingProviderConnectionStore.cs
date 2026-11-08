#nullable enable
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Documents;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Persistence of <see cref="ShippingProviderConnection"/> entities. The returned instances are copies, so callers
/// can modify them freely and persist the change only through <see cref="SaveAsync"/>.
/// </summary>
public interface IShippingProviderConnectionStore
{
    Task<IReadOnlyList<ShippingProviderConnection>> GetAllAsync();

    Task<ShippingProviderConnection?> FindByIdAsync(string id);

    Task SaveAsync(ShippingProviderConnection connection);

    Task<bool> DeleteAsync(string id);
}

public class ShippingProviderConnectionStore : IShippingProviderConnectionStore
{
    private readonly IDocumentManager<ShippingProviderConnectionsDocument> _documentManager;

    public ShippingProviderConnectionStore(IDocumentManager<ShippingProviderConnectionsDocument> documentManager) =>
        _documentManager = documentManager;

    public async Task<IReadOnlyList<ShippingProviderConnection>> GetAllAsync()
    {
        var document = await _documentManager.GetOrCreateImmutableAsync();

        return document.Connections.Values
            .OrderBy(connection => connection.Name, StringComparer.OrdinalIgnoreCase)
            .Select(connection => connection.Clone())
            .ToList();
    }

    public async Task<ShippingProviderConnection?> FindByIdAsync(string id)
    {
        if (string.IsNullOrWhiteSpace(id)) return null;

        var document = await _documentManager.GetOrCreateImmutableAsync();

        return document.Connections.TryGetValue(id, out var connection) ? connection.Clone() : null;
    }

    public async Task SaveAsync(ShippingProviderConnection connection)
    {
        ArgumentNullException.ThrowIfNull(connection);

        var document = await _documentManager.GetOrCreateMutableAsync();
        document.Connections[connection.Id] = connection.Clone();
        await _documentManager.UpdateAsync(document);
    }

    public async Task<bool> DeleteAsync(string id)
    {
        var document = await _documentManager.GetOrCreateMutableAsync();
        if (!document.Connections.Remove(id)) return false;

        await _documentManager.UpdateAsync(document);
        return true;
    }
}
