using System;
using System.Collections.Generic;
using System.Threading.Tasks;

// These types intentionally mirror OrchardCore.Secrets.Abstractions (namespace and member signatures) so they can be
// replaced by the real Orchard Core package once it is published. Remove this file when that happens.
namespace OrchardCore.Secrets;

/// <summary>
/// Marker interface for secret values.
/// </summary>
public interface ISecret
{
}

/// <summary>
/// A simple text-based secret.
/// </summary>
public class TextSecret : ISecret
{
    public string? Text { get; set; }
}

/// <summary>
/// Options for saving a secret.
/// </summary>
public class SecretSaveOptions
{
    public string? Description { get; set; }

    public DateTime? ExpiresUtc { get; set; }
}

/// <summary>
/// Metadata about a secret, without its value.
/// </summary>
public class SecretInfo
{
    public string? Name { get; set; }

    public string? Store { get; set; }

    public string? Type { get; set; }

    public string? Description { get; set; }

    public DateTime? CreatedUtc { get; set; }

    public DateTime? UpdatedUtc { get; set; }

    public DateTime? ExpiresUtc { get; set; }
}

/// <summary>
/// A storage backend for secrets.
/// </summary>
public interface ISecretStore
{
    string Name { get; }

    bool IsReadOnly { get; }

    Task<T?> GetSecretAsync<T>(string name)
        where T : class, ISecret;

    Task SaveSecretAsync<T>(string name, T secret, SecretSaveOptions? options = null)
        where T : class, ISecret;

    Task RemoveSecretAsync(string name);

    Task<IEnumerable<SecretInfo>> GetSecretInfosAsync();
}

/// <summary>
/// Entry point for reading and writing secrets across the registered stores.
/// </summary>
public interface ISecretManager
{
    Task<T?> GetSecretAsync<T>(string name)
        where T : class, ISecret;

    Task<T?> GetSecretAsync<T>(string name, string storeName)
        where T : class, ISecret;

    Task SaveSecretAsync<T>(string name, T secret, SecretSaveOptions? options = null)
        where T : class, ISecret;

    Task SaveSecretAsync<T>(string name, T secret, string storeName, SecretSaveOptions? options = null)
        where T : class, ISecret;

    Task RemoveSecretAsync(string name);

    Task RemoveSecretAsync(string name, string storeName);

    Task<IEnumerable<SecretInfo>> GetSecretInfosAsync();

    IEnumerable<ISecretStore> GetStores();
}
