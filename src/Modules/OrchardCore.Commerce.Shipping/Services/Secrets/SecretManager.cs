#nullable enable
using OrchardCore.Secrets;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services.Secrets;

/// <summary>
/// Naive <see cref="ISecretManager"/>: reads from the first store that has the secret, writes to the first writable store.
/// </summary>
public class SecretManager : ISecretManager
{
    private readonly IReadOnlyList<ISecretStore> _stores;

    public SecretManager(IEnumerable<ISecretStore> stores) => _stores = stores.ToList();

    public async Task<T?> GetSecretAsync<T>(string name)
        where T : class, ISecret
    {
        foreach (var store in _stores)
        {
            var secret = await store.GetSecretAsync<T>(name);
            if (secret != null) return secret;
        }

        return null;
    }

    public Task<T?> GetSecretAsync<T>(string name, string storeName)
        where T : class, ISecret =>
        GetStore(storeName).GetSecretAsync<T>(name);

    public Task SaveSecretAsync<T>(string name, T secret, SecretSaveOptions? options = null)
        where T : class, ISecret
    {
        var store = _stores.FirstOrDefault(store => !store.IsReadOnly)
            ?? throw new InvalidOperationException("There is no writable secret store.");
        return store.SaveSecretAsync(name, secret, options);
    }

    public Task SaveSecretAsync<T>(string name, T secret, string storeName, SecretSaveOptions? options = null)
        where T : class, ISecret =>
        GetStore(storeName).SaveSecretAsync(name, secret, options);

    public async Task RemoveSecretAsync(string name)
    {
        foreach (var store in _stores.Where(store => !store.IsReadOnly))
        {
            await store.RemoveSecretAsync(name);
        }
    }

    public Task RemoveSecretAsync(string name, string storeName) => GetStore(storeName).RemoveSecretAsync(name);

    public async Task<IEnumerable<SecretInfo>> GetSecretInfosAsync()
    {
        var result = new List<SecretInfo>();
        foreach (var store in _stores)
        {
            result.AddRange(await store.GetSecretInfosAsync());
        }

        return result;
    }

    public IEnumerable<ISecretStore> GetStores() => _stores;

    private ISecretStore GetStore(string storeName) =>
        _stores.FirstOrDefault(store => string.Equals(store.Name, storeName, StringComparison.OrdinalIgnoreCase))
        ?? throw new InvalidOperationException($"Secret store '{storeName}' was not found.");
}
