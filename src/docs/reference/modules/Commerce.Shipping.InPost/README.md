# InPost Shipping Provider (`OrchardCore.Commerce.Shipping.InPost`)

**Status: Real ShipX API integration**

InPost (ShipX API) integration for OrchardCore Commerce Shipping, covering parcel lockers
(Paczkomaty) and courier services offered by InPost in Poland.

## Overview

This module provides a shipping provider implementation for InPost, calling the real ShipX REST
API (`api-shipx-pl.easypack24.net` / `sandbox-api-shipx-pl.easypack24.net`):

- ✅ Real-time rate quotes (`POST /shipments/calculate`)
- ✅ Static service catalog (well-known ShipX service codes)
- ✅ Pickup point (parcel locker) search (`GET /v1/points`)
- ✅ Shipment creation and cancellation (`POST`/`DELETE /shipments`)
- ✅ Tracking updates (collection filter by `tracking_number`)
- ⚠️ Not yet exercised against a live ShipX sandbox account - see "Implementation Status" below

## Installation

1. Enable the `OrchardCore.Commerce.Shipping` feature.
2. Enable the `OrchardCore.Commerce.Shipping.InPost` feature.

## Configuration

### 1. Obtain InPost ShipX API Credentials

Sign up for an InPost ShipX account and generate an API access token.

You will need:

- **API Token** - Bearer token used to authenticate ShipX API requests.
- **Organization ID** - the ShipX organization the token is authorized for.

### 2. Create Provider Connection

1. Navigate to **Content > New > Shipping Provider Connection**.
2. Select **InPost** as provider.
3. Enter credentials:
   - API Token
   - Organization ID
4. Toggle **Test Mode** for the sandbox environment.
5. Click **Test Connection**.
6. Publish when healthy.

### 3. Configure Shipping Methods

Create shipping methods using InPost services (e.g., "InPost Parcel Locker", "InPost Courier
Standard").

## Supported Services

The InPost provider exposes a fixed, publicly documented set of ShipX service codes:

| Code | Description |
|---|---|
| `inpost_locker_standard` | Parcel locker (Paczkomat), standard |
| `inpost_locker_economy` | Parcel locker, economy |
| `inpost_locker_allegro` | Allegro-branded parcel locker |
| `inpost_locker_pass_thru` | Pass-thru locker |
| `inpost_courier_standard` | Standard courier |
| `inpost_courier_c2c` | Customer-to-customer courier |
| `inpost_courier_express_1000` | Courier, delivery guaranteed by 10:00 |
| `inpost_courier_express_1200` | Courier, delivery guaranteed by 12:00 |
| `inpost_courier_express_1700` | Courier, delivery guaranteed by 17:00 |
| `inpost_courier_palette` | Pallet courier |
| `inpost_courier_alcohol` | SmartCourier (age-restricted) |
| `inpost_courier_local_standard` | Local standard courier |
| `inpost_courier_local_express` | Local express courier |
| `inpost_courier_local_super_express` | Local super express courier |
| `inpost_courier_allegro` | Allegro-branded courier |
| `inpost_letter_allegro` | Allegro-branded registered mail |
| `inpost_letter_ecommerce` | E-commerce letter/parcel |

Because this catalog is fixed, `GetAvailableServicesAsync` returns it directly rather than
calling the ShipX API.

## Pickup points

Parcel locker services (`inpost_locker_*`) require selecting a pickup point (a specific
Paczkomat). The provider implements `IPickupPointProvider.SearchPickupPointsAsync`, which calls
`GET /v1/points` filtered by `relative_post_code` and sorted by `distance_to_relative_point`
(grounded in the PrestaShop plugin's `ClosestPointDataProvider`).

## Implementation Status

All six provider methods (`TestConnectionAsync`, `GetRatesAsync`, `PurchaseShipmentAsync`,
`CancelShipmentAsync`, `GetTrackingAsync`, `SearchPickupPointsAsync`) call the real ShipX API via
`InPostApiClient`. Every endpoint path, auth scheme, and request field name was grounded directly
in the reference PHP plugins (`inpost-for-woocommerce`, `inpostshipping-presta`).

A few response shapes could not be directly observed in that source (the PHP code only forwards
the raw JSON) and are parsed defensively instead of against a confirmed schema - notably the
`/shipments/calculate` "offers" array and the `/v1/points` "address" object. See the module's own
[Readme.md](https://github.com/OrchardCMS/OrchardCore.Commerce/blob/main/src/Modules/OrchardCore.Commerce.Shipping.InPost/Readme.md)
for the full list of grounding notes and remaining TODOs (extension-service wiring for COD/insurance,
credential encryption, unit tests, admin UI).

## See Also

- [Commerce Shipping Overview](../Commerce.Shipping/README.md)
- [Shipping Provider Authoring Guide](../Commerce.Shipping/provider-authoring.md)
- [Full Shipping Design](../../../../../docs/new_module/OrchardCore-Commerce-Shipping-Design.md)
