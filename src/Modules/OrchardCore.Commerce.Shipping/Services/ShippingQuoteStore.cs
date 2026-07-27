#nullable enable
using Microsoft.Extensions.Caching.Distributed;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Abstractions;
using System;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// <see cref="IDistributedCache"/>-backed implementation of <see cref="IShippingQuoteStore"/>.
/// Quotes are serialized as JSON and expire after the configured <see cref="ShippingOptions.QuoteExpirationMinutes"/>.
/// </summary>
public class ShippingQuoteStore : IShippingQuoteStore
{
    private const string KeyPrefix = "ShippingQuote:";

    private static readonly JsonSerializerOptions _jsonOptions = new(JsonSerializerDefaults.Web);

    private readonly IDistributedCache _cache;
    private readonly ShippingOptions _options;

    public ShippingQuoteStore(IDistributedCache cache, IOptionsSnapshot<ShippingOptions> options)
    {
        _cache = cache;
        _options = options.Value;
    }

    public async Task StoreQuoteAsync(ShippingQuote quote, CancellationToken cancellationToken = default)
    {
        var json = JsonSerializer.SerializeToUtf8Bytes(quote, _jsonOptions);
        var entryOptions = new DistributedCacheEntryOptions
        {
            AbsoluteExpirationRelativeToNow = TimeSpan.FromMinutes(_options.QuoteExpirationMinutes),
        };

        await _cache.SetAsync(KeyPrefix + quote.QuoteId, json, entryOptions, cancellationToken);
    }

    public async Task<ShippingQuote?> GetQuoteAsync(string quoteId, CancellationToken cancellationToken = default)
    {
        var bytes = await _cache.GetAsync(KeyPrefix + quoteId, cancellationToken);

        if (bytes is null || bytes.Length == 0)
        {
            return null;
        }

        return JsonSerializer.Deserialize<ShippingQuote>(bytes, _jsonOptions);
    }

    public Task DeleteQuoteAsync(string quoteId, CancellationToken cancellationToken = default) =>
        _cache.RemoveAsync(KeyPrefix + quoteId, cancellationToken);
}
