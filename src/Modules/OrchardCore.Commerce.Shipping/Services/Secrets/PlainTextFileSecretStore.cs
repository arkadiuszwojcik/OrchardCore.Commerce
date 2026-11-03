#nullable enable
using Microsoft.Extensions.Options;
using OrchardCore.Environment.Shell;
using OrchardCore.Environment.Shell.Configuration;
using OrchardCore.Secrets;
using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services.Secrets;

/// <summary>
/// Naive placeholder <see cref="ISecretStore"/> that keeps secrets as UNENCRYPTED JSON in the tenant's App_Data folder.
/// Replace it with the real Orchard Core Secrets implementation once available.
/// </summary>
public sealed class PlainTextFileSecretStore : ISecretStore, IDisposable
{
    public const string StoreName = "ShippingPlainText";

    private static readonly JsonSerializerOptions _jsonOptions = new() { WriteIndented = true };

    private readonly SemaphoreSlim _lock = new(1, 1);
    private readonly string _path;

    public PlainTextFileSecretStore(ShellSettings shellSettings, IOptions<ShellOptions> shellOptions) =>
        _path = Path.Combine(
            shellOptions.Value.ShellsApplicationDataPath,
            shellOptions.Value.ShellsContainerName,
            shellSettings.Name,
            "shipping-secrets.json");

    public string Name => StoreName;

    public bool IsReadOnly => false;

    public async Task<T?> GetSecretAsync<T>(string name)
        where T : class, ISecret
    {
        var entries = await LoadAsync();
        if (!entries.TryGetValue(name, out var entry) || entry.Type != typeof(T).Name) return null;

        return entry.Value.Deserialize<T>(_jsonOptions);
    }

    public async Task SaveSecretAsync<T>(string name, T secret, SecretSaveOptions? options = null)
        where T : class, ISecret
    {
        await _lock.WaitAsync();
        try
        {
            var entries = await LoadUnsafeAsync();
            var now = DateTime.UtcNow;
            entries.TryGetValue(name, out var existing);

            entries[name] = new Entry
            {
                Type = typeof(T).Name,
                Value = JsonSerializer.SerializeToElement(secret, _jsonOptions),
                Description = options?.Description,
                ExpiresUtc = options?.ExpiresUtc,
                CreatedUtc = existing?.CreatedUtc ?? now,
                UpdatedUtc = now,
            };

            await SaveUnsafeAsync(entries);
        }
        finally
        {
            _lock.Release();
        }
    }

    public async Task RemoveSecretAsync(string name)
    {
        await _lock.WaitAsync();
        try
        {
            var entries = await LoadUnsafeAsync();
            if (entries.Remove(name)) await SaveUnsafeAsync(entries);
        }
        finally
        {
            _lock.Release();
        }
    }

    public async Task<IEnumerable<SecretInfo>> GetSecretInfosAsync()
    {
        var entries = await LoadAsync();
        return entries.Select(pair => new SecretInfo
        {
            Name = pair.Key,
            Store = Name,
            Type = pair.Value.Type,
            Description = pair.Value.Description,
            CreatedUtc = pair.Value.CreatedUtc,
            UpdatedUtc = pair.Value.UpdatedUtc,
            ExpiresUtc = pair.Value.ExpiresUtc,
        }).ToList();
    }

    public void Dispose() => _lock.Dispose();

    private async Task<Dictionary<string, Entry>> LoadAsync()
    {
        await _lock.WaitAsync();
        try
        {
            return await LoadUnsafeAsync();
        }
        finally
        {
            _lock.Release();
        }
    }

    private async Task<Dictionary<string, Entry>> LoadUnsafeAsync()
    {
        if (!File.Exists(_path)) return new Dictionary<string, Entry>(StringComparer.OrdinalIgnoreCase);

        await using var stream = File.OpenRead(_path);
        var loaded = await JsonSerializer.DeserializeAsync<Dictionary<string, Entry>>(stream, _jsonOptions);
        return new Dictionary<string, Entry>(loaded ?? [], StringComparer.OrdinalIgnoreCase);
    }

    private async Task SaveUnsafeAsync(Dictionary<string, Entry> entries)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(_path)!);
        await using var stream = File.Create(_path);
        await JsonSerializer.SerializeAsync(stream, entries, _jsonOptions);
    }

    private sealed class Entry
    {
        public string? Type { get; set; }

        public JsonElement Value { get; set; }

        public string? Description { get; set; }

        public DateTime? CreatedUtc { get; set; }

        public DateTime? UpdatedUtc { get; set; }

        public DateTime? ExpiresUtc { get; set; }
    }
}
