#nullable enable
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using System;
using System.Collections.Generic;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Creates, loads, saves and deletes <see cref="ShippingProviderConnection"/> entities and builds the runtime
/// context a provider needs to use one.
/// </summary>
public interface IShippingProviderConnectionManager
{
    /// <summary>
    /// Creates a new, not yet saved connection for the given provider. Returns <see langword="null"/> when no such
    /// provider is registered.
    /// </summary>
    ShippingProviderConnection? New(string providerKey);

    Task<ShippingProviderConnection?> FindByIdAsync(string id);

    Task<IReadOnlyList<ShippingProviderConnection>> GetAllAsync();

    /// <summary>
    /// Saves the connection and writes any <see cref="ShippingProviderConnection.PendingCredentials"/> to the secret
    /// store.
    /// </summary>
    Task SaveAsync(ShippingProviderConnection connection);

    /// <summary>
    /// Deletes the connection together with its stored credentials.
    /// </summary>
    Task<bool> DeleteAsync(string id);

    /// <summary>
    /// Builds the provider context for the connection, including its stored credentials.
    /// </summary>
    Task<ShippingProviderConnectionContext> CreateContextAsync(ShippingProviderConnection connection);
}

public class ShippingProviderConnectionManager : IShippingProviderConnectionManager
{
    private readonly IShippingProviderConnectionStore _store;
    private readonly IShippingProviderRegistry _providerRegistry;
    private readonly IShippingCredentialsService _credentialsService;
    private readonly TimeProvider _timeProvider;

    public ShippingProviderConnectionManager(
        IShippingProviderConnectionStore store,
        IShippingProviderRegistry providerRegistry,
        IShippingCredentialsService credentialsService,
        TimeProvider timeProvider)
    {
        _store = store;
        _providerRegistry = providerRegistry;
        _credentialsService = credentialsService;
        _timeProvider = timeProvider;
    }

    public ShippingProviderConnection? New(string providerKey)
    {
        var provider = _providerRegistry.GetProvider(providerKey);
        if (provider is null) return null;

        return new ShippingProviderConnection
        {
            ProviderKey = provider.ProviderKey,
            CreatedUtc = _timeProvider.GetUtcNow().UtcDateTime,
        };
    }

    public Task<ShippingProviderConnection?> FindByIdAsync(string id) => _store.FindByIdAsync(id);

    public Task<IReadOnlyList<ShippingProviderConnection>> GetAllAsync() => _store.GetAllAsync();

    public async Task SaveAsync(ShippingProviderConnection connection)
    {
        if (connection.PendingCredentials.Count > 0)
        {
            var credentials = new Dictionary<string, string>(await _credentialsService.GetCredentialsAsync(connection.Id));
            foreach (var (key, value) in connection.PendingCredentials)
            {
                credentials[key] = value;
            }

            await _credentialsService.SaveCredentialsAsync(connection.Id, credentials);
            connection.PendingCredentials.Clear();
            connection.CredentialVersion++;
            connection.MarkConfigurationChanged();
        }

        await _store.SaveAsync(connection);
    }

    public async Task<bool> DeleteAsync(string id)
    {
        if (!await _store.DeleteAsync(id)) return false;

        await _credentialsService.RemoveCredentialsAsync(id);
        return true;
    }

    public async Task<ShippingProviderConnectionContext> CreateContextAsync(ShippingProviderConnection connection) =>
        connection.ToContext(await _credentialsService.GetCredentialsAsync(connection.Id));
}
