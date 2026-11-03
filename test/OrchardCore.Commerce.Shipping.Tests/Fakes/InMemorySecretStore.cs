using OrchardCore.Secrets;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Tests.Fakes;

/// <summary>
/// In-memory <see cref="ISecretStore"/> for tests.
/// </summary>
public class InMemorySecretStore : ISecretStore
{
    private readonly ConcurrentDictionary<string, ISecret> _secrets = new();

    public string Name => "InMemory";

    public bool IsReadOnly => false;

    public Task<T?> GetSecretAsync<T>(string name)
        where T : class, ISecret =>
        Task.FromResult(_secrets.GetValueOrDefault(name) as T);

    public Task SaveSecretAsync<T>(string name, T secret, SecretSaveOptions? options = null)
        where T : class, ISecret
    {
        _secrets[name] = secret;
        return Task.CompletedTask;
    }

    public Task RemoveSecretAsync(string name)
    {
        _secrets.TryRemove(name, out _);
        return Task.CompletedTask;
    }

    public Task<IEnumerable<SecretInfo>> GetSecretInfosAsync() =>
        Task.FromResult(_secrets.Keys.Select(key => new SecretInfo { Name = key, Store = Name }));
}
