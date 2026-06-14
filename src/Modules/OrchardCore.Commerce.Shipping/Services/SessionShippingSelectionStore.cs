using Microsoft.AspNetCore.Http;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using System;
using System.Collections.Concurrent;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

public class SessionShippingSelectionStore : IShippingSelectionStore
{
    private static readonly ConcurrentDictionary<string, ShippingSelection> _fallbackStore = new(StringComparer.Ordinal);
    private const string SessionPrefix = "oc-commerce-shipping-selection:";

    private readonly IHttpContextAccessor _hca;

    public SessionShippingSelectionStore(IHttpContextAccessor hca) => _hca = hca;

    public Task<ShippingSelection?> GetAsync(string shoppingCartId, CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrWhiteSpace(shoppingCartId)) return Task.FromResult<ShippingSelection?>(null);

        var key = GetSessionKey(shoppingCartId);
        if (_hca.HttpContext?.Session is { IsAvailable: true } session)
        {
            var json = session.GetString(key);
            if (string.IsNullOrWhiteSpace(json)) return Task.FromResult<ShippingSelection?>(null);

            var selection = JsonSerializer.Deserialize<ShippingSelection>(json);
            return Task.FromResult(selection);
        }

        _fallbackStore.TryGetValue(shoppingCartId, out var fallbackSelection);
        return Task.FromResult(fallbackSelection);
    }

    public Task SetAsync(string shoppingCartId, ShippingSelection selection, CancellationToken cancellationToken = default)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(shoppingCartId);
        ArgumentNullException.ThrowIfNull(selection);

        var key = GetSessionKey(shoppingCartId);
        if (_hca.HttpContext?.Session is { IsAvailable: true } session)
        {
            session.SetString(key, JsonSerializer.Serialize(selection));
            return Task.CompletedTask;
        }

        _fallbackStore[shoppingCartId] = selection;
        return Task.CompletedTask;
    }

    public Task RemoveAsync(string shoppingCartId, CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrWhiteSpace(shoppingCartId)) return Task.CompletedTask;

        var key = GetSessionKey(shoppingCartId);
        if (_hca.HttpContext?.Session is { IsAvailable: true } session)
        {
            session.Remove(key);
            return Task.CompletedTask;
        }

        _fallbackStore.TryRemove(shoppingCartId, out _);
        return Task.CompletedTask;
    }

    private static string GetSessionKey(string shoppingCartId) => SessionPrefix + shoppingCartId;
}