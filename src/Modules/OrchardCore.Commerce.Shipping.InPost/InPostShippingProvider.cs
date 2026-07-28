using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.MoneyDataType;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Commerce.Shipping.InPost.Client.Models;
using OrchardCore.Commerce.Shipping.InPost.Constants;
using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Net;
using System.Net.Http;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost;

/// <summary>
/// InPost ShipX shipping provider. Talks to the real ShipX REST API via <see cref="InPostApiClient"/>; see that
/// class's remarks for exactly which endpoints/shapes are grounded in the reference PHP plugins versus inferred.
/// </summary>
public class InPostShippingProvider :
    IShippingRateProvider,
    IShippingServiceCatalogProvider,
    IShipmentProvider,
    IShippingTrackingProvider,
    IPickupPointProvider
{
    // The static ShipX service catalog. InPost, unlike some carriers, exposes a fixed, publicly
    // documented list of service codes rather than an account-specific catalog, so this can be
    // returned without calling the API. See InPostServiceCodes for the individual code meanings.
    private static readonly IReadOnlyList<ShippingServiceDescriptor> _services = new[]
    {
        new ShippingServiceDescriptor(
            InPostServiceCodes.LockerStandard,
            "Parcel locker - standard",
            "Delivery to an InPost parcel locker (Paczkomat)."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.LockerEconomy,
            "Parcel locker - economy",
            "Slower, lower-cost delivery to an InPost parcel locker."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.LockerAllegro,
            "Parcel locker - Allegro",
            "Allegro-branded InPost parcel locker delivery."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.LockerPassThru,
            "Parcel locker - pass-thru"),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierStandard,
            "Courier - standard",
            "Door-to-door courier delivery."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierC2C,
            "Courier - customer to customer",
            "Courier delivery between two private individuals, dropped off at a parcel locker or point."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierExpress1000,
            "Courier - express until 10:00",
            "Courier delivery guaranteed by 10:00."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierExpress1200,
            "Courier - express until 12:00",
            "Courier delivery guaranteed by 12:00."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierExpress1700,
            "Courier - express until 17:00",
            "Courier delivery guaranteed by 17:00."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierPalette,
            "Courier - pallet",
            "Standard pallet courier shipment."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierAlcohol,
            "SmartCourier (alcohol)",
            "Age-restricted courier delivery for alcohol, requires signature and age verification.",
            RequiresSignature: true),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierLocalStandard,
            "Courier - local standard"),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierLocalExpress,
            "Courier - local express"),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierLocalSuperExpress,
            "Courier - local super express"),
        new ShippingServiceDescriptor(
            InPostServiceCodes.CourierAllegro,
            "Courier - Allegro",
            "Allegro-branded InPost courier delivery."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.LetterAllegro,
            "Registered mail - Allegro",
            "Allegro-branded InPost registered mail shipment."),
        new ShippingServiceDescriptor(
            InPostServiceCodes.LetterEcommerce,
            "E-commerce letter/parcel",
            "Lightweight e-commerce letter/parcel shipment."),
    };

    // Grounded in Status.php (both plugins). ShipX has additional statuses beyond these (e.g. "dispatched_by_sender",
    // "out_for_delivery", "avizo") that weren't directly observable in the reference source; those fall back to
    // ShipmentTrackingStatus.Unknown below rather than being guessed.
    private static readonly IReadOnlyDictionary<string, ShipmentTrackingStatus> _statusMap =
        new Dictionary<string, ShipmentTrackingStatus>(StringComparer.OrdinalIgnoreCase)
        {
            ["created"] = ShipmentTrackingStatus.Pending,
            ["offers_prepared"] = ShipmentTrackingStatus.Pending,
            ["offer_selected"] = ShipmentTrackingStatus.Pending,
            ["confirmed"] = ShipmentTrackingStatus.InfoReceived,
            ["collected_from_sender"] = ShipmentTrackingStatus.InTransit,
            ["taken_by_courier"] = ShipmentTrackingStatus.InTransit,
            ["delivered"] = ShipmentTrackingStatus.Delivered,
            ["canceled"] = ShipmentTrackingStatus.Exception,
            ["not_found"] = ShipmentTrackingStatus.Unknown,
        };

    private static readonly (string Template, decimal MaxLength, decimal MaxWidth, decimal MaxHeight, decimal MaxWeightKg)[] _lockerTemplates =
    {
        (InPostParcelTemplates.Small, 8m, 38m, 64m, 25m),
        (InPostParcelTemplates.Medium, 19m, 38m, 64m, 25m),
        (InPostParcelTemplates.Large, 41m, 38m, 64m, 25m),
    };

    private static readonly (string Template, decimal MaxLength, decimal MaxWidth, decimal MaxHeight, decimal MaxWeightKg) _xlargeTemplate =
        (InPostParcelTemplates.XLarge, 50m, 50m, 80m, 25m);

    // Separate template family for inpost_letter_allegro only, capped at 10 kg. letter_c does not use a bounding
    // box like letter_a/letter_b; it instead allows any shape as long as the sum of the 3 dimensions is <= 160cm
    // (handled separately in SelectLetterTemplate rather than via this table).
    private static readonly (string Template, decimal MaxLength, decimal MaxWidth, decimal MaxHeight, decimal MaxWeightKg)[] _letterTemplates =
    {
        (InPostParcelTemplates.LetterA, 8m, 38m, 64m, 10m),
        (InPostParcelTemplates.LetterB, 19m, 38m, 64m, 10m),
    };

    private const decimal LetterCMaxDimensionSumCm = 160m;
    private const decimal LetterMaxWeightKg = 10m;

    // Governs how BuildParcel picks (or omits) a ShipX dimension template for a given service, per
    // docs/Rozmiary i usługi dla przesyłek.md.
    private enum ParcelSizingStrategy
    {
        // small/medium/large only - locker services (standard, economy, allegro, pass-thru).
        LockerSmallMediumLarge,

        // small/medium/large plus xlarge - inpost_courier_c2c only.
        LockerWithXLarge,

        // letter_a/letter_b/letter_c - inpost_letter_allegro only.
        Letter,

        // No template exists/is recommended for this service (couriers, pallet, courier_allegro, and any
        // service not covered by the doc); always send raw dimensions and weight instead.
        RawDimensionsOnly,
    }

    private static ParcelSizingStrategy GetSizingStrategy(string serviceId) => serviceId switch
    {
        // inpost_locker_economy is treated the same as inpost_locker_standard for sizing purposes, per explicit
        // confirmation even though the doc's locker-services table doesn't list it separately.
        InPostServiceCodes.LockerStandard or
        InPostServiceCodes.LockerEconomy or
        InPostServiceCodes.LockerAllegro or
        InPostServiceCodes.LockerPassThru => ParcelSizingStrategy.LockerSmallMediumLarge,
        InPostServiceCodes.CourierC2C => ParcelSizingStrategy.LockerWithXLarge,
        InPostServiceCodes.LetterAllegro => ParcelSizingStrategy.Letter,
        _ => ParcelSizingStrategy.RawDimensionsOnly,
    };

    private readonly InPostApiClient _apiClient;
    private readonly ILogger<InPostShippingProvider> _logger;

    public InPostShippingProvider(InPostApiClient apiClient, ILogger<InPostShippingProvider> logger)
    {
        _apiClient = apiClient;
        _logger = logger;
    }

    public string ProviderId => "InPost";

    public ShippingProviderDescriptor GetDescriptor() =>
        new(
            ProviderId: "InPost",
            DisplayName: "InPost",
            Capabilities: ShippingProviderCapabilities.RateQuotes |
                          ShippingProviderCapabilities.PurchaseLabels |
                          ShippingProviderCapabilities.Tracking |
                          ShippingProviderCapabilities.PickupPoints |
                          ShippingProviderCapabilities.CancelShipment,
            RequiredCredentialKeys: new[] { "ApiToken", "OrganizationId" },
            DocumentationUrl: null);

    public async Task<ShippingProviderConnectionHealth> TestConnectionAsync(
        ShippingProviderConnectionContext context,
        CancellationToken cancellationToken = default)
    {
        try
        {
            var organization = await _apiClient.GetOrganizationAsync(context, cancellationToken);
            return new ShippingProviderConnectionHealth(
                Status: ShippingConnectionHealthStatus.Healthy,
                LastChecked: DateTimeOffset.UtcNow,
                Message: organization?.Name is { } name ? $"Connected to organization '{name}'." : "Connected.");
        }
        catch (InPostApiException exception) when (exception.StatusCode is HttpStatusCode.Unauthorized or HttpStatusCode.Forbidden)
        {
            return new ShippingProviderConnectionHealth(
                Status: ShippingConnectionHealthStatus.Unhealthy,
                LastChecked: DateTimeOffset.UtcNow,
                Message: "The ShipX API token or organization ID is invalid.");
        }
        catch (InPostApiException exception)
        {
            _logger.LogWarning(exception, "InPost ShipX connection test failed for connection {ConnectionId}.", context.ConnectionId);
            return new ShippingProviderConnectionHealth(
                Status: ShippingConnectionHealthStatus.Degraded,
                LastChecked: DateTimeOffset.UtcNow,
                Message: exception.Message);
        }
        catch (Exception exception) when (exception is HttpRequestException or TaskCanceledException)
        {
            _logger.LogWarning(exception, "InPost ShipX connection test could not reach the API for connection {ConnectionId}.", context.ConnectionId);
            return new ShippingProviderConnectionHealth(
                Status: ShippingConnectionHealthStatus.Unknown,
                LastChecked: DateTimeOffset.UtcNow,
                Message: "Could not reach the ShipX API.");
        }
    }

    public async Task<IReadOnlyList<ProviderShippingRate>> GetRatesAsync(
        ShippingProviderConnectionContext context,
        ProviderRateRequest request,
        CancellationToken cancellationToken = default)
    {
        var isLockerDelivery = !string.IsNullOrEmpty(request.PickupPointId);
        var candidateServices = _services
            .Where(service => service.ServiceId.Contains("locker", StringComparison.Ordinal) == isLockerDelivery)
            .ToList();
        if (candidateServices.Count == 0) return Array.Empty<ProviderShippingRate>();

        var shipmentRequests = candidateServices
            .Select(service => BuildShipmentRequest(request.OriginAddress, request.DestinationAddress, request.Packages, service.ServiceId, request.PickupPointId, reference: null))
            .ToList();

        IReadOnlyList<JsonElement> priced;
        try
        {
            priced = await _apiClient.CalculatePricesAsync(context, shipmentRequests, cancellationToken);
        }
        catch (InPostApiException exception)
        {
            _logger.LogWarning(exception, "InPost ShipX rate calculation failed for connection {ConnectionId}.", context.ConnectionId);
            return Array.Empty<ProviderShippingRate>();
        }

        var rates = new List<ProviderShippingRate>();
        for (var index = 0; index < priced.Count; index++)
        {
            var element = priced[index];
            var service = TryMatchService(element, candidateServices) ?? candidateServices[Math.Min(index, candidateServices.Count - 1)];
            if (TryExtractOfferPrice(element, out var amount, out var currencyCode))
            {
                var currency = new CurrencyProvider().GetCurrency(currencyCode) ?? Currency.UnspecifiedCurrency;
                var price = new Amount(amount, currency);
                rates.Add(new ProviderShippingRate(
                    ServiceId: service.ServiceId,
                    ServiceName: service.ServiceName,
                    BasePrice: price,
                    Charges: new[] { new ShippingCharge("Base rate", price) }));
            }
        }

        return rates;
    }

    public ProviderRateCachePolicy GetRateCachePolicy() =>
        new(EnableCaching: true, CacheDuration: TimeSpan.FromMinutes(15));

    public async Task<IReadOnlyList<ShippingServiceDescriptor>> GetAvailableServicesAsync(
        ShippingProviderConnectionContext context,
        CancellationToken cancellationToken = default)
    {
        // InPost's service catalog is a fixed, publicly documented list rather than one resolved
        // from the account, so it can be returned directly instead of calling the ShipX API.
        await Task.CompletedTask;
        return _services;
    }

    public async Task<ShipmentPurchaseResponse> PurchaseShipmentAsync(
        ShippingProviderConnectionContext context,
        ShipmentPurchaseRequest request,
        CancellationToken cancellationToken = default)
    {
        var shipmentRequest = BuildShipmentRequest(
            request.OriginAddress,
            request.DestinationAddress,
            request.Packages,
            request.ServiceId,
            request.PickupPoint?.PickupPointId,
            reference: request.OrderId);

        InPostShipmentResponse? created;
        try
        {
            created = await _apiClient.CreateShipmentAsync(context, shipmentRequest, cancellationToken);
        }
        catch (InPostApiException exception)
        {
            _logger.LogWarning(exception, "InPost ShipX shipment purchase failed for order {OrderId}.", request.OrderId);
            throw;
        }

        if (created?.Id is not { } shipmentId)
        {
            throw new InvalidOperationException("The ShipX API did not return a shipment ID.");
        }

        Amount? actualCost = null;
        if (created.SelectedOffer is { } selectedOffer && TryExtractOfferPrice(selectedOffer, out var amount, out var currencyCode))
        {
            var currency = new CurrencyProvider().GetCurrency(currencyCode) ?? Currency.UnspecifiedCurrency;
            actualCost = new Amount(amount, currency);
        }

        return new ShipmentPurchaseResponse(
            ShipmentId: shipmentId.ToString(CultureInfo.InvariantCulture),
            TrackingNumber: created.TrackingNumber,
            // Grounded in CarrierUpdater::TRACKING_URL (PrestaShop plugin) - the public customer-facing tracking page.
            TrackingUrl: created.TrackingNumber is { } trackingNumber
                ? $"https://inpost.pl/sledzenie-przesylek?number={Uri.EscapeDataString(trackingNumber)}"
                : null,
            ActualCost: actualCost,
            Metadata: created.Status is { } status ? new Dictionary<string, object> { ["Status"] = status } : null);
    }

    public async Task<bool> CancelShipmentAsync(
        ShippingProviderConnectionContext context,
        string shipmentId,
        CancellationToken cancellationToken = default)
    {
        try
        {
            // ShipX only allows cancellation while the shipment has not yet been confirmed/dispatched; a rejection
            // surfaces as an InPostApiException and is treated as "could not cancel" rather than propagated.
            await _apiClient.CancelShipmentAsync(context, shipmentId, cancellationToken);
            return true;
        }
        catch (InPostApiException exception)
        {
            _logger.LogWarning(exception, "InPost ShipX shipment {ShipmentId} could not be canceled.", shipmentId);
            return false;
        }
    }

    public async Task<ShippingTrackingResponse> GetTrackingAsync(
        ShippingProviderConnectionContext context,
        string trackingNumber,
        CancellationToken cancellationToken = default)
    {
        InPostShipmentResponse? shipment;
        try
        {
            shipment = await _apiClient.FindShipmentByTrackingNumberAsync(context, trackingNumber, cancellationToken);
        }
        catch (InPostApiException exception)
        {
            _logger.LogWarning(exception, "InPost ShipX tracking lookup failed for tracking number {TrackingNumber}.", trackingNumber);
            shipment = null;
        }

        var status = shipment?.Status is { } shipXStatus && _statusMap.TryGetValue(shipXStatus, out var mapped)
            ? mapped
            : ShipmentTrackingStatus.Unknown;

        return new ShippingTrackingResponse(
            TrackingNumber: trackingNumber,
            CurrentStatus: status,
            Events: Array.Empty<ShippingTrackingEvent>(),
            ActualDelivery: status == ShipmentTrackingStatus.Delivered ? DateTimeOffset.UtcNow : null);
    }

    public async Task<IReadOnlyList<PickupPoint>> SearchPickupPointsAsync(
        ShippingProviderConnectionContext context,
        PickupPointSearchRequest request,
        CancellationToken cancellationToken = default)
    {
        if (string.IsNullOrEmpty(request.DestinationAddress.PostalCode)) return Array.Empty<PickupPoint>();

        // Query parameters grounded in ClosestPointDataProvider (PrestaShop plugin): relative_post_code,
        // sort_by=distance_to_relative_point, sort_order, limit.
        var query = new Dictionary<string, string>
        {
            ["relative_post_code"] = request.DestinationAddress.PostalCode,
            ["sort_by"] = "distance_to_relative_point",
            ["sort_order"] = "asc",
            ["limit"] = request.MaxResults.ToString(CultureInfo.InvariantCulture),
        };

        IReadOnlyList<JsonElement> points;
        try
        {
            points = await _apiClient.SearchPointsAsync(context, query, cancellationToken);
        }
        catch (InPostApiException exception)
        {
            _logger.LogWarning(exception, "InPost ShipX pickup point search failed for connection {ConnectionId}.", context.ConnectionId);
            return Array.Empty<PickupPoint>();
        }

        return points.Select(MapPickupPoint).Where(point => point is not null).Select(point => point!).ToList();
    }

    private static InPostShipmentRequest BuildShipmentRequest(
        AddressSnapshot origin,
        AddressSnapshot destination,
        IReadOnlyList<ShippingPackage> packages,
        string serviceId,
        string? pickupPointId,
        string? reference)
    {
        var isLocker = serviceId.Contains("locker", StringComparison.Ordinal);
        var customAttributes = isLocker && !string.IsNullOrEmpty(pickupPointId)
            ? new InPostCustomAttributes(InPostSendingMethods.ParcelLocker, TargetPoint: pickupPointId)
            : new InPostCustomAttributes(InPostSendingMethods.DispatchOrder);

        return new InPostShipmentRequest(
            Receiver: MapContact(destination),
            Parcels: packages.Select(package => BuildParcel(package, serviceId)).ToList(),
            Service: serviceId,
            CustomAttributes: customAttributes,
            Sender: MapContact(origin),
            Reference: reference);
    }

    private static InPostContact MapContact(AddressSnapshot address)
    {
        var name = address.Name ?? string.Empty;
        var spaceIndex = name.IndexOf(' ', StringComparison.Ordinal);
        var firstName = spaceIndex > 0 ? name[..spaceIndex] : name;
        var lastName = spaceIndex > 0 ? name[(spaceIndex + 1)..] : name;

        return new InPostContact(
            FirstName: firstName,
            LastName: lastName,
            Email: address.Email ?? string.Empty,
            Phone: address.Phone ?? string.Empty,
            Address: new InPostAddress(
                Street: address.Line1 ?? string.Empty,
                BuildingNumber: address.Line2 ?? string.Empty,
                City: address.City ?? string.Empty,
                PostCode: address.PostalCode ?? string.Empty,
                CountryCode: address.CountryCode ?? string.Empty),
            CompanyName: address.Company);
    }

    private static InPostParcel BuildParcel(ShippingPackage package, string serviceId)
    {
        var weightKg = package.TotalWeight.ConvertTo(WeightUnit.Kilogram).Value;
        InPostDimensions? dimensions = null;
        string? template = null;

        if (package.Dimensions is { } dimensionsValue)
        {
            var centimeters = dimensionsValue.ConvertTo(DimensionUnit.Centimeter);
            dimensions = new InPostDimensions(centimeters.Length * 10m, centimeters.Width * 10m, centimeters.Height * 10m);

            var strategy = GetSizingStrategy(serviceId);
            template = strategy switch
            {
                ParcelSizingStrategy.LockerSmallMediumLarge =>
                    SelectBoundingBoxTemplate(centimeters.Length, centimeters.Width, centimeters.Height, weightKg, _lockerTemplates),
                ParcelSizingStrategy.LockerWithXLarge =>
                    SelectBoundingBoxTemplate(centimeters.Length, centimeters.Width, centimeters.Height, weightKg, _lockerTemplates)
                        ?? SelectBoundingBoxTemplate(centimeters.Length, centimeters.Width, centimeters.Height, weightKg, new[] { _xlargeTemplate }),
                ParcelSizingStrategy.Letter => SelectLetterTemplate(centimeters.Length, centimeters.Width, centimeters.Height, weightKg),
                ParcelSizingStrategy.RawDimensionsOnly => null,
                _ => null,
            };
        }

        return new InPostParcel(new InPostWeight(weightKg), Id: package.PackageId, Template: template, Dimensions: dimensions);
    }

    // Picks the smallest matching dimension template (small/medium/large, or xlarge for c2c) that fits the
    // package, regardless of orientation, by comparing sorted dimensions, and whose weight cap isn't exceeded.
    // Returns null (send raw dimensions, no template) if nothing fits - e.g. the package is oversized or
    // overweight for every template in the given set.
    private static string? SelectBoundingBoxTemplate(
        decimal lengthCm,
        decimal widthCm,
        decimal heightCm,
        decimal weightKg,
        (string Template, decimal MaxLength, decimal MaxWidth, decimal MaxHeight, decimal MaxWeightKg)[] templates)
    {
        Span<decimal> sorted = stackalloc decimal[3] { lengthCm, widthCm, heightCm };
        sorted.Sort();

        foreach (var (template, maxLength, maxWidth, maxHeight, maxWeightKg) in templates)
        {
            if (weightKg > maxWeightKg) continue;

            Span<decimal> maxSorted = stackalloc decimal[3] { maxLength, maxWidth, maxHeight };
            maxSorted.Sort();

            if (sorted[0] <= maxSorted[0] && sorted[1] <= maxSorted[1] && sorted[2] <= maxSorted[2])
            {
                return template;
            }
        }

        return null;
    }

    // inpost_letter_allegro's own template family: letter_a/letter_b use a bounding-box fit like the locker
    // templates, but letter_c instead allows any shape as long as the sum of the 3 dimensions is <= 160cm. All
    // three are capped at 10 kg. Returns null (send raw dimensions, no template) if nothing fits.
    private static string? SelectLetterTemplate(decimal lengthCm, decimal widthCm, decimal heightCm, decimal weightKg)
    {
        if (weightKg > LetterMaxWeightKg) return null;

        if (SelectBoundingBoxTemplate(lengthCm, widthCm, heightCm, weightKg, _letterTemplates) is { } boundingBoxTemplate)
        {
            return boundingBoxTemplate;
        }

        return lengthCm + widthCm + heightCm <= LetterCMaxDimensionSumCm ? InPostParcelTemplates.LetterC : null;
    }

    // The candidate shipment's service code is echoed back on the calculate response element when available;
    // this correlates results to the originally requested service rather than relying purely on array order.
    private static ShippingServiceDescriptor? TryMatchService(JsonElement element, IReadOnlyList<ShippingServiceDescriptor> candidates) =>
        element.ValueKind == JsonValueKind.Object &&
        element.TryGetProperty("service", out var serviceElement) &&
        serviceElement.ValueKind == JsonValueKind.String
            ? candidates.FirstOrDefault(candidate => candidate.ServiceId == serviceElement.GetString())
            : null;

    // The exact JSON shape of ShipX "offers" wasn't directly observable in the reference PHP source (it only
    // forwards the raw response), so several plausible key paths are tried defensively: offers[0].price.{amount,
    // currency}, offers[0].rate.{amount,currency} and offers[0].{amount,currency}; likewise for a bare "price"/
    // "selected_offer" object instead of an "offers" array.
    private static bool TryExtractOfferPrice(JsonElement shipmentOrOffer, out decimal amount, out string currency)
    {
        amount = 0;
        currency = string.Empty;

        var offer = shipmentOrOffer;
        if (shipmentOrOffer.ValueKind == JsonValueKind.Object &&
            shipmentOrOffer.TryGetProperty("offers", out var offers) &&
            offers.ValueKind == JsonValueKind.Array &&
            offers.GetArrayLength() > 0)
        {
            offer = offers[0];
        }

        if (offer.ValueKind != JsonValueKind.Object) return false;

        foreach (var priceContainerName in new[] { "price", "rate", null })
        {
            var priceContainer = offer;
            if (priceContainerName is not null)
            {
                if (!offer.TryGetProperty(priceContainerName, out priceContainer)) continue;
            }

            if (priceContainer.ValueKind != JsonValueKind.Object) continue;

            if (TryGetDecimal(priceContainer, "amount", out amount) &&
                priceContainer.TryGetProperty("currency", out var currencyElement) &&
                currencyElement.ValueKind == JsonValueKind.String)
            {
                currency = currencyElement.GetString() ?? string.Empty;
                return true;
            }
        }

        return false;
    }

    private static bool TryGetDecimal(JsonElement element, string propertyName, out decimal value)
    {
        value = 0;
        if (!element.TryGetProperty(propertyName, out var property)) return false;

        return property.ValueKind switch
        {
            JsonValueKind.Number => property.TryGetDecimal(out value),
            JsonValueKind.String => decimal.TryParse(property.GetString(), NumberStyles.Number, CultureInfo.InvariantCulture, out value),
            _ => false,
        };
    }

    // Point resource fields grounded in ShipX/Resource/Point.php: name (id), address (object), location_description,
    // opening_hours, distance. The exact keys inside "address" weren't directly observable, so common candidates
    // (line1/street, city, post_code) are tried defensively.
    private static PickupPoint? MapPickupPoint(JsonElement point)
    {
        if (point.ValueKind != JsonValueKind.Object) return null;
        if (!point.TryGetProperty("name", out var nameElement) || nameElement.ValueKind != JsonValueKind.String) return null;

        var pickupPointId = nameElement.GetString();
        if (string.IsNullOrEmpty(pickupPointId)) return null;

        var locationName = point.TryGetProperty("location_description", out var descriptionElement) &&
            descriptionElement.ValueKind == JsonValueKind.String
                ? descriptionElement.GetString() ?? pickupPointId
                : pickupPointId;

        AddressSnapshot? address = null;
        if (point.TryGetProperty("address", out var addressElement) && addressElement.ValueKind == JsonValueKind.Object)
        {
            address = new AddressSnapshot(
                Line1: GetString(addressElement, "line1") ?? GetString(addressElement, "street"),
                City: GetString(addressElement, "city"),
                PostalCode: GetString(addressElement, "post_code"),
                CountryCode: GetString(addressElement, "country_code"));
        }

        var openingHours = GetString(point, "opening_hours");
        var distance = point.TryGetProperty("distance", out var distanceElement) && distanceElement.ValueKind == JsonValueKind.Number
            ? distanceElement.GetRawText()
            : null;

        return new PickupPoint(
            PickupPointId: pickupPointId,
            LocationName: locationName,
            Address: address ?? new AddressSnapshot(),
            OpeningHours: openingHours,
            DistanceFromDestination: distance);
    }

    private static string? GetString(JsonElement element, string propertyName) =>
        element.TryGetProperty(propertyName, out var property) && property.ValueKind == JsonValueKind.String
            ? property.GetString()
            : null;
}

