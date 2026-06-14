using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.InPost.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Options;
using OrchardCore.Commerce.Shipping.Exceptions;
using OrchardCore.Commerce.Shipping.Models;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Services;

public class InPostShippingClient : IInPostShippingClient
{
    private const string ProviderName = "InPost";

    private static readonly IReadOnlyList<ShipXServiceMapping> _serviceMappings =
    [
        new(
            ShipXServiceId: "inpost_locker_standard",
            MethodCode: "inpost_parcel_locker",
            DisplayName: "InPost Paczkomat",
            Description: "Pickup point delivery via InPost lockers.",
            ExternalMethodCode: "easypack_parcel_machines"),
        new(
            ShipXServiceId: "inpost_courier_standard",
            MethodCode: "inpost_courier",
            DisplayName: "InPost Courier",
            Description: "Courier delivery via InPost.",
            ExternalMethodCode: "easypack_shipping_courier")
    ];

    private readonly IHttpClientFactory _httpClientFactory;
    private readonly IOptions<InPostShippingOptions> _options;
    private readonly ILogger<InPostShippingClient> _logger;

    public InPostShippingClient(
        IHttpClientFactory httpClientFactory,
        IOptions<InPostShippingOptions> options,
        ILogger<InPostShippingClient> logger)
    {
        _httpClientFactory = httpClientFactory;
        _options = options;
        _logger = logger;
    }

    public async Task<IReadOnlyList<ShippingRateOption>> GetRatesAsync(
        ShippingRateRequest request,
        CancellationToken cancellationToken = default)
    {
        var options = _options.Value;
        ValidateConfiguration(options);

        using var client = BuildClient(options);
        var availableServiceIds = await GetOrganizationServicesAsync(client, options.OrganizationId, cancellationToken);

        if (availableServiceIds.Count == 0)
        {
            return [];
        }

        var serviceCatalog = await GetServicesCatalogAsync(client, cancellationToken);
        var serviceById = serviceCatalog.ToDictionary(service => service.Id, StringComparer.OrdinalIgnoreCase);

        var rates = new List<ShippingRateOption>();
        ShippingProviderException? lastCalculationException = null;
        foreach (var mapping in _serviceMappings)
        {
            if (!availableServiceIds.Contains(mapping.ShipXServiceId)) continue;

            serviceById.TryGetValue(mapping.ShipXServiceId, out var serviceInfo);

            try
            {
                var dynamicPrice = await CalculatePriceAsync(client, request, mapping, options, cancellationToken);
                if (dynamicPrice is null) continue;

                rates.Add(new ShippingRateOption(
                    Provider: ProviderName,
                    MethodCode: mapping.MethodCode,
                    DisplayName: !string.IsNullOrWhiteSpace(serviceInfo?.Name) ? serviceInfo.Name : mapping.DisplayName,
                    Price: dynamicPrice.Value,
                    Currency: options.Currency,
                    Description: !string.IsNullOrWhiteSpace(serviceInfo?.Description)
                        ? serviceInfo.Description
                        : mapping.Description,
                    ExternalMethodCode: mapping.ExternalMethodCode));
            }
            catch (ShippingProviderException ex)
            {
                lastCalculationException = ex;
                _logger.LogWarning(
                    "ShipX price calculation failed for service {ServiceId} ({MethodCode}): {Message}",
                    mapping.ShipXServiceId,
                    mapping.MethodCode,
                    ex.Message);
            }
        }

        if (rates.Count == 0 && lastCalculationException is not null)
        {
            throw lastCalculationException;
        }

        return rates;
    }

    private static void ValidateConfiguration(InPostShippingOptions options)
    {
        if (string.IsNullOrWhiteSpace(options.ApiToken))
        {
            throw new ShippingProviderException(
                provider: ProviderName,
                code: "shipx_config_missing_token",
                message: "InPost ShipX API token is not configured.",
                status: 500);
        }

        if (options.OrganizationId <= 0)
        {
            throw new ShippingProviderException(
                provider: ProviderName,
                code: "shipx_config_missing_organization",
                message: "InPost ShipX organization id is not configured.",
                status: 500);
        }

        if (string.IsNullOrWhiteSpace(options.SenderCountryCode) ||
            string.IsNullOrWhiteSpace(options.SenderStreet) ||
            string.IsNullOrWhiteSpace(options.SenderCity) ||
            string.IsNullOrWhiteSpace(options.SenderPostalCode))
        {
            throw new ShippingProviderException(
                provider: ProviderName,
                code: "shipx_config_missing_sender",
                message: "InPost sender address configuration is incomplete.",
                status: 500);
        }

        if (options.DefaultParcelWeightKg <= 0 ||
            options.DefaultParcelLengthMm <= 0 ||
            options.DefaultParcelWidthMm <= 0 ||
            options.DefaultParcelHeightMm <= 0)
        {
            throw new ShippingProviderException(
                provider: ProviderName,
                code: "shipx_config_invalid_default_parcel",
                message: "InPost default parcel dimensions and weight must be positive values.",
                status: 500);
        }
    }

    private HttpClient BuildClient(InPostShippingOptions options)
    {
        var client = _httpClientFactory.CreateClient();
        client.BaseAddress = new Uri(GetBaseUrl(options), UriKind.Absolute);
        client.Timeout = TimeSpan.FromSeconds(Math.Clamp(options.RequestTimeoutSeconds, 5, 120));
        client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", options.ApiToken.Trim());
        return client;
    }

    private static string GetBaseUrl(InPostShippingOptions options)
    {
        if (!string.IsNullOrWhiteSpace(options.BaseUrlOverride))
        {
            return options.BaseUrlOverride.TrimEnd('/');
        }

        var country = options.Country?.Trim().ToUpperInvariant();
        var environment = options.Environment?.Trim().ToLowerInvariant();

        if (country == InPostShippingOptions.CountryUk)
        {
            return environment == InPostShippingOptions.EnvironmentSandbox
                ? "https://sandbox-api-shipx-uk.easypack24.net/v1"
                : "https://api-shipx-uk.easypack24.net/v1";
        }

        return environment == InPostShippingOptions.EnvironmentSandbox
            ? "https://sandbox-api-shipx-pl.easypack24.net/v1"
            : "https://api-shipx-pl.easypack24.net/v1";
    }

    private async Task<HashSet<string>> GetOrganizationServicesAsync(
        HttpClient client,
        int organizationId,
        CancellationToken cancellationToken)
    {
        using var response = await client.GetAsync($"/organizations/{organizationId}", cancellationToken);
        var body = await response.Content.ReadAsStringAsync(cancellationToken);
        if (!response.IsSuccessStatusCode)
        {
            throw CreateShipXException(response, body);
        }

        using var document = JsonDocument.Parse(body);
        if (!document.RootElement.TryGetProperty("services", out var servicesElement) ||
            servicesElement.ValueKind != JsonValueKind.Array)
        {
            _logger.LogWarning("InPost ShipX organization response did not contain a services array.");
            return [];
        }

        var serviceIds = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
        foreach (var serviceElement in servicesElement.EnumerateArray())
        {
            if (serviceElement.ValueKind == JsonValueKind.String)
            {
                var serviceId = serviceElement.GetString();
                if (!string.IsNullOrWhiteSpace(serviceId)) serviceIds.Add(serviceId);
            }
        }

        return serviceIds;
    }

    private async Task<IReadOnlyList<ShipXServiceInfo>> GetServicesCatalogAsync(
        HttpClient client,
        CancellationToken cancellationToken)
    {
        using var response = await client.GetAsync("/services", cancellationToken);
        var body = await response.Content.ReadAsStringAsync(cancellationToken);
        if (!response.IsSuccessStatusCode)
        {
            throw CreateShipXException(response, body);
        }

        using var document = JsonDocument.Parse(body);
        if (document.RootElement.ValueKind != JsonValueKind.Array)
        {
            return [];
        }

        var services = new List<ShipXServiceInfo>();
        foreach (var serviceElement in document.RootElement.EnumerateArray())
        {
            var id = serviceElement.TryGetProperty("id", out var idElement) && idElement.ValueKind == JsonValueKind.String
                ? idElement.GetString()
                : null;

            if (string.IsNullOrWhiteSpace(id)) continue;

            var name = serviceElement.TryGetProperty("name", out var nameElement) && nameElement.ValueKind == JsonValueKind.String
                ? nameElement.GetString()
                : null;
            var description = serviceElement.TryGetProperty("description", out var descriptionElement) && descriptionElement.ValueKind == JsonValueKind.String
                ? descriptionElement.GetString()
                : null;

            services.Add(new ShipXServiceInfo(id, name, description));
        }

        return services;
    }

    private static ShippingProviderException CreateShipXException(HttpResponseMessage response, string responseBody)
    {
        var status = (int)response.StatusCode;
        var correlationId = response.Headers.TryGetValues("x-request-id", out var values)
            ? values.FirstOrDefault()
            : null;

        try
        {
            using var document = JsonDocument.Parse(responseBody);
            var root = document.RootElement;

            var code = root.TryGetProperty("type", out var typeElement) && typeElement.ValueKind == JsonValueKind.String
                ? typeElement.GetString()
                : "shipx_request_failed";

            var title = root.TryGetProperty("title", out var titleElement) && titleElement.ValueKind == JsonValueKind.String
                ? titleElement.GetString()
                : null;

            var detail = root.TryGetProperty("detail", out var detailElement) && detailElement.ValueKind == JsonValueKind.String
                ? detailElement.GetString()
                : null;

            var message = !string.IsNullOrWhiteSpace(title)
                ? title
                : "InPost ShipX request failed.";

            IReadOnlyDictionary<string, string[]>? errors = null;
            if (root.TryGetProperty("errors", out var errorsElement) && errorsElement.ValueKind == JsonValueKind.Object)
            {
                var parsedErrors = new Dictionary<string, string[]>(StringComparer.OrdinalIgnoreCase);
                foreach (var property in errorsElement.EnumerateObject())
                {
                    if (property.Value.ValueKind != JsonValueKind.Array) continue;
                    parsedErrors[property.Name] = property.Value
                        .EnumerateArray()
                        .Where(item => item.ValueKind == JsonValueKind.String)
                        .Select(item => item.GetString())
                        .Where(item => !string.IsNullOrWhiteSpace(item))
                        .Cast<string>()
                        .ToArray();
                }

                errors = parsedErrors;
            }

            return new ShippingProviderException(
                provider: ProviderName,
                code: code ?? "shipx_request_failed",
                message: message,
                status: status,
                providerType: title,
                providerDetail: detail,
                correlationId: correlationId,
                errors: errors);
        }
        catch (JsonException)
        {
            return new ShippingProviderException(
                provider: ProviderName,
                code: "shipx_request_failed",
                message: $"InPost ShipX request failed with status {status}.",
                status: status,
                providerDetail: string.IsNullOrWhiteSpace(responseBody) ? null : responseBody,
                correlationId: correlationId);
        }
    }

    private async Task<decimal?> CalculatePriceAsync(
        HttpClient client,
        ShippingRateRequest request,
        ShipXServiceMapping mapping,
        InPostShippingOptions options,
        CancellationToken cancellationToken)
    {
        using var response = await client.PostAsync(
            $"/organizations/{options.OrganizationId}/shipments/calculate",
            JsonContentFor(BuildCalculatePayload(request, mapping, options)),
            cancellationToken);

        var responseBody = await response.Content.ReadAsStringAsync(cancellationToken);
        if (!response.IsSuccessStatusCode)
        {
            throw CreateShipXException(response, responseBody);
        }

        using var document = JsonDocument.Parse(responseBody);
        if (!TryExtractPrice(document.RootElement, out var calculatedPrice))
        {
            _logger.LogWarning(
                "ShipX calculate response for service {ServiceId} did not include a recognized price field.",
                mapping.ShipXServiceId);
            return null;
        }

        return calculatedPrice;
    }

    private static StringContent JsonContentFor(object payload)
    {
        var serialized = JsonSerializer.Serialize(payload);
        return new StringContent(serialized, System.Text.Encoding.UTF8, "application/json");
    }

    private static object BuildCalculatePayload(
        ShippingRateRequest request,
        ShipXServiceMapping mapping,
        InPostShippingOptions options)
    {
        var recipientAddress = request.ShippingAddress;
        var recipientCountryCode = (recipientAddress?.Region ?? options.Country)?.ToUpperInvariant();
        var serviceIsLocker = mapping.ShipXServiceId.Equals("inpost_locker_standard", StringComparison.OrdinalIgnoreCase);

        object destination = serviceIsLocker && !string.IsNullOrWhiteSpace(options.DefaultLockerPointId)
            ? new
            {
                countryCode = recipientCountryCode ?? InPostShippingOptions.CountryPl,
                pointId = options.DefaultLockerPointId,
            }
            : new
            {
                countryCode = recipientCountryCode ?? InPostShippingOptions.CountryPl,
                street = recipientAddress?.StreetAddress1 ?? "Unknown",
                houseNumber = recipientAddress?.StreetAddress2 ?? "1",
                city = recipientAddress?.City ?? "Unknown",
                postalCode = recipientAddress?.PostalCode ?? "00-000",
            };

        var sender = new
        {
            companyName = options.SenderCompanyName,
            firstName = options.SenderFirstName,
            lastName = options.SenderLastName,
            email = options.SenderEmail,
            phone = options.SenderPhone,
        };

        var recipient = new
        {
            companyName = recipientAddress?.Company,
            firstName = recipientAddress?.Name ?? "Customer",
            lastName = recipientAddress?.Name ?? "Customer",
            email = options.SenderEmail,
            phone = options.SenderPhone,
        };

        var origin = new
        {
            countryCode = options.SenderCountryCode,
            street = options.SenderStreet,
            houseNumber = options.SenderBuildingNumber,
            city = options.SenderCity,
            postalCode = options.SenderPostalCode,
        };

        object customAttributes = serviceIsLocker
            ? new
            {
                sendingMethod = "parcel_locker",
                targetPoint = options.DefaultLockerPointId,
            }
            : new
            {
                sendingMethod = "dispatch_order",
            };

        var shipment = new
        {
            sender,
            recipient,
            origin,
            destination,
            service = mapping.ShipXServiceId,
            reference = request.ShoppingCartId,
            externalCustomerId = "orchardcore-commerce",
            customAttributes,
            parcels = new[]
            {
                new
                {
                    type = "STANDARD",
                    dimensions = new
                    {
                        length = options.DefaultParcelLengthMm,
                        width = options.DefaultParcelWidthMm,
                        height = options.DefaultParcelHeightMm,
                        unit = "MM",
                    },
                    weight = new
                    {
                        amount = options.DefaultParcelWeightKg,
                        unit = "KG",
                    },
                }
            },
        };

        return shipment;
    }

    private static bool TryExtractPrice(JsonElement element, out decimal price)
    {
        switch (element.ValueKind)
        {
            case JsonValueKind.Object:
            {
                if (TryExtractPriceFromObject(element, out price)) return true;

                foreach (var property in element.EnumerateObject())
                {
                    if (TryExtractPrice(property.Value, out price)) return true;
                }

                break;
            }

            case JsonValueKind.Array:
                foreach (var item in element.EnumerateArray())
                {
                    if (TryExtractPrice(item, out price)) return true;
                }
                break;
        }

        price = default;
        return false;
    }

    private static bool TryExtractPriceFromObject(JsonElement element, out decimal price)
    {
        if (TryGetDecimalProperty(element, "calculated_charge_amount", out price)) return true;
        if (TryGetDecimalProperty(element, "calculated_charge_amount_non_commission", out price)) return true;

        if (element.TryGetProperty("price", out var priceElement) &&
            priceElement.ValueKind == JsonValueKind.Object &&
            TryGetDecimalProperty(priceElement, "amount", out price))
        {
            return true;
        }

        if (element.TryGetProperty("total", out var totalElement) &&
            totalElement.ValueKind == JsonValueKind.Object &&
            TryGetDecimalProperty(totalElement, "amount", out price))
        {
            return true;
        }

        return false;
    }

    private static bool TryGetDecimalProperty(JsonElement element, string propertyName, out decimal value)
    {
        if (element.TryGetProperty(propertyName, out var property))
        {
            if (property.ValueKind == JsonValueKind.Number && property.TryGetDecimal(out value))
            {
                return true;
            }

            if (property.ValueKind == JsonValueKind.String &&
                decimal.TryParse(property.GetString(), out value))
            {
                return true;
            }
        }

        value = default;
        return false;
    }

    private sealed record ShipXServiceInfo(string Id, string? Name, string? Description);

    private sealed record ShipXServiceMapping(
        string ShipXServiceId,
        string MethodCode,
        string DisplayName,
        string Description,
        string ExternalMethodCode);
}