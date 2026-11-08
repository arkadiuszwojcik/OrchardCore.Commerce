#nullable enable
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.ContentManagement;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Orchestrating implementation of <see cref="IShippingRateService"/>.
/// Flow: load ShippingMethod content item → load the shipping provider connection → build context
/// → check rate cache → call provider → cache results → map to <see cref="ShippingQuote"/> list.
/// </summary>
public class ShippingRateService : IShippingRateService
{
    private readonly IContentManager _contentManager;
    private readonly IShippingProviderConnectionManager _connectionManager;
    private readonly IShippingProviderRegistry _providerRegistry;
    private readonly IShippingRateCache _rateCache;
    private readonly IShippingQuoteStore _quoteStore;
    private readonly ShippingOptions _options;
    private readonly TimeProvider _timeProvider;

    public ShippingRateService(
        IContentManager contentManager,
        IShippingProviderConnectionManager connectionManager,
        IShippingProviderRegistry providerRegistry,
        IShippingRateCache rateCache,
        IShippingQuoteStore quoteStore,
        IOptionsSnapshot<ShippingOptions> options,
        TimeProvider timeProvider)
    {
        _timeProvider = timeProvider;
        _contentManager = contentManager;
        _connectionManager = connectionManager;
        _providerRegistry = providerRegistry;
        _rateCache = rateCache;
        _quoteStore = quoteStore;
        _options = options.Value;
    }

    public async Task<IReadOnlyList<ShippingQuote>> GetAvailableRatesAsync(
        string shippingMethodId,
        AddressSnapshot originAddress,
        AddressSnapshot destinationAddress,
        IReadOnlyList<ShippingPackage> packages,
        CancellationToken cancellationToken = default)
    {
        // 1. Load ShippingMethod content item.
        var methodItem = await _contentManager.GetAsync(shippingMethodId);
        if (methodItem is null) return Array.Empty<ShippingQuote>();

        if (!methodItem.TryGet<ShippingMethodPart>(out var methodPart) || !methodPart.IsEnabled.Value) return Array.Empty<ShippingQuote>();

        var connectionId = methodPart.ProviderConnectionId.Text;
        if (string.IsNullOrEmpty(connectionId)) return Array.Empty<ShippingQuote>();

        // 2. Load the shipping provider connection.
        var connection = await _connectionManager.FindByIdAsync(connectionId);
        if (connection is null || !connection.IsEnabled) return Array.Empty<ShippingQuote>();

        // 3. Resolve provider.
        var providerKey = connection.ProviderKey;
        if (string.IsNullOrEmpty(providerKey)) return Array.Empty<ShippingQuote>();

        if (_providerRegistry.GetProvider(providerKey) is not IShippingRateProvider rateProvider)
        {
            return Array.Empty<ShippingQuote>();
        }

        // 4. Build connection context from the connection's settings and stored secrets.
        var context = await _connectionManager.CreateContextAsync(connection);

        // 5. Build cache key and try cache.
        var cachePolicy = rateProvider.GetRateCachePolicy();
        IReadOnlyList<ProviderShippingRate>? cachedRates = null;

        if (_options.EnableRateCaching && cachePolicy.EnableCaching)
        {
            var cacheKey = BuildCacheKey(context, originAddress, destinationAddress, packages, cachePolicy);
            var request = new ProviderRateRequest(connectionId, originAddress, destinationAddress, packages);
            var effectivePolicy = cachePolicy with
            {
                CacheDuration = cachePolicy.CacheDuration > TimeSpan.Zero
                    ? cachePolicy.CacheDuration
                    : TimeSpan.FromMinutes(_options.DefaultRateCacheDurationMinutes),
            };

            cachedRates = await _rateCache.GetOrCreateAsync(
                cacheKey,
                effectivePolicy,
                ct => rateProvider.GetRatesAsync(context, request, ct),
                cancellationToken);
        }
        else
        {
            var request = new ProviderRateRequest(connectionId, originAddress, destinationAddress, packages);
            cachedRates = await rateProvider.GetRatesAsync(context, request, cancellationToken);
        }

        // 7. Map provider rates to ShippingQuotes, filtering by optional ServiceId.
        var serviceFilter = methodPart.ServiceId.Text;
        var expiresAt = _timeProvider.GetUtcNow().AddMinutes(_options.QuoteExpirationMinutes);

        var quotes = cachedRates
            .Where(r => string.IsNullOrEmpty(serviceFilter) || r.ServiceId == serviceFilter)
            .Select(r => new ShippingQuote(
                QuoteId: Guid.NewGuid().ToString("N"),
                ShippingMethodId: shippingMethodId,
                ProviderConnectionId: connectionId,
                ServiceId: r.ServiceId,
                ServiceName: r.ServiceName,
                TotalPrice: r.BasePrice,
                Charges: r.Charges,
                DeliveryEstimate: r.DeliveryEstimate,
                OriginAddress: originAddress,
                DestinationAddress: destinationAddress,
                ExpiresAt: expiresAt))
            .ToList();

        // 8. Store quotes so they can be retrieved and validated later.
        foreach (var quote in quotes)
        {
            await _quoteStore.StoreQuoteAsync(quote, cancellationToken);
        }

        return quotes;
    }

    public async Task<bool> ValidateQuoteAsync(
        string quoteId,
        ShippingQuoteValidationMode validationMode,
        CancellationToken cancellationToken = default)
    {
        if (validationMode == ShippingQuoteValidationMode.None) return true;

        var quote = await _quoteStore.GetQuoteAsync(quoteId, cancellationToken);
        if (quote is null) return false;

        // Check expiry.
        if (quote.ExpiresAt.HasValue && _timeProvider.GetUtcNow() > quote.ExpiresAt.Value)
        {
            return false;
        }

        if (validationMode == ShippingQuoteValidationMode.ValidatePrice)
        {
            return true; // Price has not changed as long as the quote is still in the store.
        }

        // RefetchAndCompare: re-fetch and compare price.
        var fresh = await GetAvailableRatesAsync(
            quote.ShippingMethodId,
            quote.OriginAddress!,
            quote.DestinationAddress!,
            Array.Empty<ShippingPackage>(),
            cancellationToken);

        return fresh.Any(r => r.ServiceId == quote.ServiceId && r.TotalPrice == quote.TotalPrice);
    }

    private static string BuildCacheKey(
        ShippingProviderConnectionContext connection,
        AddressSnapshot origin,
        AddressSnapshot destination,
        IReadOnlyList<ShippingPackage> packages,
        ProviderRateCachePolicy policy)
    {
        var sb = new StringBuilder(connection.ConnectionId);
        sb.Append('|').Append(connection.ProviderKey)
            .Append('|').Append(connection.ConfigurationRevision)
            .Append('|').Append(connection.CredentialVersion);

        if (policy.CacheByAddress)
        {
            sb.Append('|').Append(origin.PostalCode).Append('|').Append(origin.CountryCode);
            sb.Append('|').Append(destination.PostalCode).Append('|').Append(destination.CountryCode);
        }

        if (policy.CacheByWeight)
        {
            var totalGrams = packages.Sum(p => p.TotalWeight.ConvertTo(WeightUnit.Gram).Value);
            sb.Append('|').Append(totalGrams.ToString("F0"));
        }

        // Use a short hash to keep the key compact.
        var hash = SHA256.HashData(Encoding.UTF8.GetBytes(sb.ToString()));
        return Convert.ToHexString(hash)[..16];
    }
}
