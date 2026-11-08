# OrchardCore.Commerce.Shipping.InPost

**Status: Real ShipX API integration**

InPost (ShipX API) shipping provider for OrchardCore Commerce. Supports parcel lockers
(Paczkomaty), courier, pallet, and letter services offered by InPost in Poland.

## Implementation Status

✅ Provider registration and descriptor
✅ Interface implementations (`IShippingRateProvider`, `IShippingServiceCatalogProvider`,
   `IShipmentProvider`, `IShippingTrackingProvider`, `IPickupPointProvider`)
✅ Static service catalog (fixed, publicly documented ShipX service codes)
✅ `InPostApiClient` (`Client/InPostApiClient.cs`): typed `HttpClient` wrapper for the ShipX REST
   API - Bearer token auth, sandbox/production base URL selection, JSON (snake_case) request/response
   mapping, and the ShipX error-envelope convention.
✅ `TestConnectionAsync`, `GetRatesAsync`, `PurchaseShipmentAsync`, `CancelShipmentAsync`,
   `GetTrackingAsync`, `SearchPickupPointsAsync` all call the real ShipX API.

### Grounding notes / known limitations

Every endpoint path, request field name, auth scheme, and status value below was taken directly
from the reference PHP plugins (`inpost-for-woocommerce`, `inpostshipping-presta`), not guessed.
A few response shapes could not be directly observed in that source (the PHP code merely forwards
the raw JSON) and are therefore parsed defensively rather than with a strict schema:

- **Rate offers** (`/shipments/calculate` response) fields are read via best-effort `JsonElement` lookups (see
  `TryExtractOfferPrice` in `InPostShippingProvider.cs`). Verify them against a live sandbox account before
  production use. Pickup points use the typed `InPostPoint` model (see "Searching points").
- **Parcel template selection** (`small`/`medium`/`large`/`xlarge`) uses the dimension/weight
  thresholds documented in `ShipX_Shipment_Parcel_Model` comments.
- Sandbox vs. production is selected from the connection's `IsTestMode` flag (`context.IsTestMode`),
  which the shared connection editor of the core shipping module sets.
- Additional services (COD, insurance, SMS/e-mail notifications) are modeled in the ShipX client
  (`InPostMoney`, `InPostShipmentRequest.Cod/Insurance`) but not yet wired up from
  `ShippingExtensionConfiguration` - see "Future extension points" below.

## TODO

1. Wire `ShippingExtensionConfiguration` (COD, insurance, notifications) into
   `PurchaseShipmentAsync`'s `InPostShipmentRequest`.
2. Verify the `/shipments/calculate` response shape against a live ShipX sandbox
   account and tighten the defensive parsing in `TryExtractOfferPrice` accordingly.
3. Add credential encryption for the API token.
4. Write unit tests with mocked ShipX responses (see `orchardcore-unit-test` skill).
5. Add admin UI for InPost-specific settings (default sending method, additional services).
6. Consider implementing label retrieval (`GET .../shipments/{id}/label`) if
   `IShippingLabelProvider` is added to this provider.

## Credentials

InPost's ShipX API uses a Bearer token plus a numeric organization ID:

- `ApiToken` - ShipX API access token. A secret, stored through the secret store (`context.Credentials`).
- `OrganizationId` - the InPost ShipX organization ID the token is authorized for. A plain setting stored in the
  connection's properties (`InPostConnectionSettings`, read with `context.GetSettings<InPostConnectionSettings>()`).
  Both are edited in the InPost connection editor (`InPostConnectionDisplayDriver`).

Sandbox (`https://sandbox-api-shipx-pl.easypack24.net`) vs. production
(`https://api-shipx-pl.easypack24.net`) is selected via `context.IsTestMode`.

## Well-known service codes

See `Constants/InPostServiceCodes.cs` for the full list, including:

| Code | Description |
|---|---|
| `inpost_locker_standard` | Parcel locker (Paczkomat), standard |
| `inpost_locker_economy` | Parcel locker, economy |
| `inpost_courier_standard` | Standard courier |
| `inpost_courier_c2c` | Customer-to-customer courier |
| `inpost_courier_express_1000/1200/1700` | Time-guaranteed courier |
| `inpost_courier_palette` | Pallet courier |
| `inpost_courier_alcohol` | SmartCourier (age-restricted) |
| `inpost_courier_local_standard/express/super_express` | Local courier variants |

## Sending methods

See `Constants/InPostSendingMethods.cs`:

- `dispatch_order` - courier picks up the parcel from the sender.
- `parcel_locker` - sender drops the parcel off at a parcel locker.
- `pop` - sender drops the parcel off at a partner point of service.

## Searching shipments

`InPostApiClient.SearchShipmentsAsync(context, search, cancellationToken)` calls
`GET /v1/organizations/{id}/shipments` and returns an `InPostShipmentPage` (`Items` plus the `Count`, `Page` and
`PerPage` of the ShipX collection envelope, null for a bare-array response). The filters are described by
`InPostShipmentSearch`; every property is optional and `ToQuery()` turns the set ones into the ShipX parameters
(lists comma separated, ISO 8601 times, invariant numbers). It covers all filters of the
[ShipX search documentation](https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/11731073/Wyszukiwanie+i+sortowanie+przesy+ek),
with `Receiver` and `Sender` (`InPostPartySearch`) for the `receiver_*` / `sender_*` parameters.

```csharp
var page = await client.SearchShipmentsAsync(context, new InPostShipmentSearch
{
    Statuses = ["confirmed", "offers_prepared"],
    CreatedFrom = DateTimeOffset.UtcNow.AddDays(-7),
    Receiver = new InPostPartySearch { City = "Kraków" },
    SortBy = InPostShipmentSortBy.CreatedAt,
    SortOrder = InPostSortOrder.Descending,
    PerPage = 25,
}, cancellationToken);
```

`FindShipmentByTrackingNumberAsync` (used for tracking) is a shortcut for a search by `TrackingNumber`.

**Not confirmed:** the paging parameter names (`page`, `per_page`) and the `count` / `page` / `per_page` envelope
fields are not on the search documentation page; they follow the Points documentation, where they are described,
and are assumed to behave the same for shipments. Verify them against the sandbox.
Sorting uses the documented `sort_by` / `sort_order` values (`InPostShipmentSortBy`, `InPostSortOrder`).

## Searching points

`InPostApiClient.SearchPointsAsync(context, search, cancellationToken)` searches the InPost Points resource
(parcel lockers, PaczkoPunkty, branches) and returns an `InPostPointPage` (`Items` plus `Count`, `Page`, `PerPage`
and `TotalPages`). `GetPointAsync(context, "KRA010", cancellationToken)` returns one point by name, or null. The
filters are described by `InPostPointSearch` and cover everything in the
[Points documentation](https://dokumentacja-inpost.atlassian.net/wiki/spaces/PL/pages/18153470/Zas+b+Points+Paczkomat+PaczkoPunkty):
names, `InPostPointType`, `InPostPointFunction`, partner IDs, post codes / cities / provinces, update dates,
location (`RelativePoint` or `RelativePostCode` with `MaxDistance` and `Limit`), `InPostPointSortBy`,
`InPostSortOrder`, `Page` and `PerPage`. `ToQuery()` rejects combinations the API documents as invalid (`UpdatedTo`
without `UpdatedFrom`, `MaxDistance` over 50 km, `PerPage` over 500).

```csharp
var page = await client.SearchPointsAsync(context, new InPostPointSearch
{
    RelativePostCode = "30-394",
    Types = [InPostPointType.ParcelLocker],
    Functions = [InPostPointFunction.ParcelCollect],
    MaxDistance = 2000,
    SortBy = InPostPointSortBy.DistanceToRelativePoint,
    Limit = 5,
}, cancellationToken);
```

`SearchPickupPointsAsync` of the provider (`IPickupPointProvider`) uses this search around the destination post code,
nearest first.

Points are served by a separate gateway, selected by the connection's test mode: `https://api.inpost.pl` in
production and `https://sandbox-api-gateway-pl.easypack24.net` in the sandbox. It uses the same bearer token, which
needs the Points access (the sandbox rejects requests without a token with 403). `InPostPoint` holds the documented
attributes that matter for choosing a point; `type`, `status` and `functions` stay strings so values InPost adds
later do not break reading, and `display_name`, which the documentation lists, is missing from sandbox responses
(`DisplayName` is null there).

## Future extension points (not implemented)

InPost's ShipX API also supports additional services not modeled yet, such as insurance,
cash-on-delivery (COD), SMS/e-mail notifications, Saturday delivery, and hour-window delivery.
These map to `ShippingExtensionConfiguration`/`ShippingExtensionData` on the abstractions and can
be added incrementally.

## Usage

1. Enable the `OrchardCore.Commerce.Shipping` feature.
2. Enable the `OrchardCore.Commerce.Shipping.InPost` feature.
3. Create a shipping provider connection under Commerce > Shipping > Provider Connections.
4. Select "InPost" as provider.
5. Enter the API token and organization ID.
6. Configure shipping methods using InPost services.

---

**Note:** The ShipX HTTP integration is real and grounded in the reference PHP plugins, but has not
been exercised against a live ShipX sandbox account in this repository. Validate credentials,
response shapes (see "Grounding notes" above), and error handling against a real account before
production use.

