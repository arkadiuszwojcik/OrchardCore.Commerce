# Shipping module - decision log

Status values: **Pending** (needs a decision from the module owner), **Decided** (recorded answer), **Implemented**.

Every item below describes a place where the current code and design document v2.3
(`OrchardCore-Commerce-Shipping-Design.md`) disagree, or a new decision required by the hardening plan.
"Recommendation" is a suggestion only. No contract is changed until the status is **Decided**.

| # | Topic | Status |
|---|---|---|
| D1 | Provider identifier name | Decided: rename to `ProviderKey` |
| D2 | Provider descriptor: method vs property, shape | Decided: recommendation |
| D3 | Connection health test: on the provider vs separate service | Decided: recommendation |
| D4 | Connection context shape | Decided: recommendation |
| D5 | Capability flags enum vs interfaces only | Decided: compute from interfaces |
| D6 | Capability method names and flag names | Decided: recommendation |
| D7 | Idempotency key, reconciliation and uncertain results | Decided: recommendation |
| D8 | Secrets: Orchard Core Secrets instead of a custom store | Decided |
| D9 | How `CredentialVersion` / revision is derived | Decided: recommendation |
| D10 | Missing physical data policy (resolver and packer) | Decided: recommendation |
| D11 | Rate cache: in-memory vs distributed | Decided: recommendation |
| D12 | Provider registration lifetime | Decided: recommendation |

---

## D1 - Provider identifier name

- **Code:** `IShippingProvider.ProviderId` (`string`). `ShippingProviderConnectionPart.ProviderId`. InPost returns `"InPost"`.
- **Design (4.1, 9.1):** `ProviderKey`, a stable, non-localized, case-insensitively unique machine identifier.
- **Impact:** Abstractions public API, connection part field name (stored data), registry, editor view model, recipes.
- **Recommendation:** keep `ProviderId` in code (it is already persisted in content items) and update the design to say `ProviderId`; make the comparison case-insensitive as the design intends.
- **Decision:** the design wins. Rename `ProviderId` to `ProviderKey` in `IShippingProvider`, `ShippingProviderDescriptor`, the connection part and everything that uses them. Because the part field name is persisted, the rename needs a data migration (step 4 migration) for existing connection items. Comparison is case-insensitive.

## D2 - Provider descriptor

- **Code:** `ShippingProviderDescriptor GetDescriptor()` method. The record has `ProviderId`, `DisplayName` (plain `string`), `Capabilities`, `RequiredCredentialKeys`, `ProviderIconUrl`, `DocumentationUrl`, `Metadata`.
- **Design (9.1):** `Descriptor` property; `DisplayName` is a `LocalizedString`; has `SupportsSandbox`, `ImplementationVersion`, `DocumentationUri`.
- **Impact:** every provider; `ImplementationVersion` feeds the rate cache key (see step 8 of the plan).
Keep `DisplayName` as `string` unless localization of provider names is required.
- **Decision:** accepted as recommended.

## D3 - Connection health

- **Code:** `IShippingProvider.TestConnectionAsync(context, ct)` on every provider. No cached health service, and the core rate service does not consult health.
- **Design (9.2):** `IShippingProviderConnectionHealthService` with cached results, bounded refresh, and per-connection suppression; providers are not asked on every checkout.
- **Impact:** resilience boundary in step 5.
Checkout then reads cached health only.
- **Decision:** accepted as recommended.

## D4 - Connection context

- **Code:** `ShippingProviderConnectionContext(ConnectionId, IDictionary<string,string> Credentials, NormalizationProfile?, Metadata?)`.
- **Design (9.2):** `ContentItemId`, `ProviderKey`, `Environment`, `ConfigurationRevision`, `CredentialVersion`, `Secrets`, `Configuration`.
- **Impact:** every provider call, rate cache key, step 4 (secrets) and step 8 (cache key).
Make the dictionaries read-only.
- **Decision:** accepted as recommended.

## D5 - Capability flags vs interfaces

- **Code:** `ShippingProviderCapabilities` flags are declared in the descriptor **and** capability interfaces exist. Nothing checks that they agree.
- **Design (4.1):** the registry validates that declarations match implemented interfaces and quarantines mismatches.
- **Options:** (a) keep both and add the validation (design); (b) compute flags from implemented interfaces and drop the declared value.
- **Recommendation:** (b) is simpler, but the declared flags also express "installed integration can support it" independently of effective capability. Choose (a) if admin screens need declared flags before an instance exists; otherwise choose (b).
- **Decision:** option (b). Capabilities are computed from the implemented capability interfaces; the declared `Capabilities` value is removed from `ShippingProviderDescriptor`. The registry exposes the computed value for admin screens. The design (4.1, 9.1) must be updated to drop the declared projection and the mismatch validation.

## D6 - Capability method and flag names

- **Code:** `IShipmentProvider.PurchaseShipmentAsync(...)`, `CancelShipmentAsync(context, string shipmentId)` returning `bool`, `IShippingTrackingProvider.GetTrackingAsync`, `IShippingServiceCatalogProvider.GetAvailableServicesAsync`, `IPickupPointProvider.SearchPickupPointsAsync`; flags `RateQuotes`, `PurchaseLabels`, `Tracking`, `PickupPoints`, `CancelShipment`. The code has no separate label provider interface (labels are returned from purchase).
- **Design (9.3-9.7, 4.1):** `CreateShipmentAsync`, `CancelShipmentAsync(request)`, `TrackAsync`, `GetServicesAsync`, `FindPickupPointsAsync`, `IShippingLabelProvider`; flags `Rates`, `ShipmentCreation`, `Labels`, etc.
- **Impact:** public provider contract; the Dhl module.
and update the design names.
- **Decision:** accepted as recommended.

## D7 - Idempotency, reconciliation, uncertain results

- **Code:** `ShipmentPurchaseRequest(OrderId, QuoteId, OriginAddress, DestinationAddress, Packages, ServiceId, ...)` has no idempotency key. InPost sends `OrderId` as `reference`. There is no reconciliation capability and no way to express an uncertain result; a timeout throws.
- **Design (9.5, 9.6, 19.3):** operation ID and idempotency key on every request; optional `IShipmentReconciliationProvider`; core guarantees at-least-once with deduplication.
- **Impact:** step 6 (endpoint) and step 12 (InPost).
Where a provider has no reconciliation, block automatic retry.
- **Decision:** accepted as recommended: idempotency key persisted before the carrier call, an uncertain-result category, optional reconciliation, and no automatic retry for providers without reconciliation.

## D8 - Secrets (decided)

- **Decision:** use Orchard Core Secrets (`OrchardCore.Secrets.Abstractions`: `ISecretManager`, `ISecretStore`, `ISecretTypeProvider`) behind a thin internal adapter. The design's own secret store with rotation (design section 18) is not built.
- **Consequence:** `ShippingProviderConnectionPart.CredentialsJson` is removed; non-secret values stay as plain connection configuration; a data migration moves existing values; a secrets feature dependency is added to the manifest.
- **Open verification (step 4) - resolved:** Orchard Core 3.0.1 does not ship the Secrets module (no `OrchardCore.Secrets*` package, no source in v3.0.1). The upstream `main` interfaces are mirrored in `Shipping.Abstractions/Secrets/Secrets.cs` (same `OrchardCore.Secrets` namespace; delete that file when the real package is available). `PlainTextFileSecretStore` and `SecretManager` are naive placeholders: credentials are stored as UNENCRYPTED JSON in the tenant's App_Data. `IShippingCredentialsService` is the only consumer; it keeps one `TextSecret` per connection named `Shipping.Connection.{contentItemId}`. The admin editor treats credentials as write-only (blank keeps the stored value). `CredentialsJson` is removed from `ShippingProviderConnectionPart`; migrating existing values is not done because the module is unpublished.

## D9 - Credential version / configuration revision

- **Code:** neither exists. The rate cache key uses only the connection ID.
- **Design:** `ConfigurationRevision` and `CredentialVersion` on the connection and in the cache key.
unless Orchard Secrets provides its own version metadata.
- **Decision:** accepted as recommended.

## D10 - Missing physical data policy

- **Code:** `ShippingPhysicalDataResolver` returns `null` for missing weight or dimensions. `ShippingPackerService` treats a null weight as zero and ignores null dimensions. The resolver's doc says the packer falls back to "module defaults", which does not happen.
- **Options:** (a) fail the quote request with a clear validation error; (b) substitute configured defaults and flag the quote as estimated; (c) current behavior.
- **Recommendation:** (a) by default, with (b) as an explicit per-store option. Zero weight is never a safe default because it underprices the shipment.
- **Decision:** accepted as recommended: fail the quote with a validation error, with optional per-store defaults.

## D11

- **Code:** `ShippingRateCache` over `IMemoryCache`, registered as a singleton. Quotes use `IDistributedCache`.
- **Impact:** multi-instance deployments get different rate caches and repeated provider calls.
- **Options:** keep in-memory (simple, per instance) or move to `IDistributedCache` (consistent, needs serialization).
revisit if the app runs on several instances.
- **Decision:** accepted as recommended.

## D12 - Provider registration lifetime

- **Code:** InPost registers the same class six times as transient, once per interface. `ShippingProviderRegistry` is a singleton built from `IEnumerable<IShippingProvider>`, so it captures a transient provider and its typed `HttpClient`.
- **Options:** register each provider once and alias interfaces; make the registry scoped; or have the registry resolve providers per call from a factory.
This also lets the typed `HttpClient` rotate handlers normally.
- **Decision:** accepted as recommended.
- **D1 ProviderKey:** done. `ProviderId` renamed to `ProviderKey` everywhere; no data migration (module unpublished).
- **D5 Capabilities:** done. Computed from implemented interfaces (`ShippingProviderCapabilitiesExtensions.Compute`, `IShippingProviderRegistry.GetCapabilities`); the declared descriptor value was removed.
- **D9 Revision:** done. `ConfigurationRevision` and `CredentialVersion` live on the connection part, are bumped by the admin editor, and are part of the connection context and the rate cache key.
- **Rate cache:** `IShippingRateCache.GetOrCreateAsync` is single-flight per key. Failures and empty results are not cached.
- **D7 Idempotency:** `IShipmentPurchaseService` persists `IdempotencyKey` and `Pending` before the carrier call. Status after the call: `Purchased`; `Failed` when the provider throws `ShippingProviderRejectedException` (definite rejection, retry allowed); `Uncertain` for any other exception (automatic retry blocked; the endpoint returns 409). `Purchased`, `Pending`, `Uncertain` and `Cancelled` shipments never call the carrier again. The workflow task and the API endpoint both use the service.
- **InPost:** 30 s client timeout; GET and `/shipments/calculate` retry up to 3 times on 5xx/429/network errors; `POST /shipments` and `DELETE` never retry; definite 4xx purchase errors map to `ShippingProviderRejectedException`; the carrier `reference` is `{OrderId}/{IdempotencyKey}`; test mode comes from the connection context.
- **Not done:** reconciliation provider (`IShipmentReconciliationProvider`) to resolve `Uncertain` shipments; cancel-endpoint state checks; health caching; design document text still mentions the old names.

## D13 - Provider connections as plain entities with provider-owned editors

- **Context:** a connection used to be a content item (`ShippingProviderConnectionPart`). Connections are configuration, not content, and the core module hard-coded the fields every provider needs.
- **Decision:** `ShippingProviderConnection` is an `Entity` kept with all other connections in one `ShippingProviderConnectionsDocument` (`IDocumentManager`), managed through `IShippingProviderConnectionManager`. The shape follows the URL Rewriting module: the core module owns the list, the Add popup (one card per registered `IShippingProvider`) and the shared fields (name, enabled, test mode); each provider module registers its own `DisplayDriver<ShippingProviderConnection>` for the rest of the editor.
- **Provider data:** non-secret settings go to `Entity.Properties` through `Put<T>` (keyed by the settings type name) and reach the provider as `context.Metadata`, read with `context.GetSettings<T>()`. Secrets stay in the secret store; editors queue them with `SetCredential` and the manager writes them on save, so an invalid form stores nothing.
- **Dropped:** `NormalizationProfileJson` and `MetadataJson` (nothing used them) and the free-form credentials JSON field.
- **Not done:** data migration (the module is unpublished); the connection list uses plain rendering because the `admin-list` shape and the `breadcrumb` tag helper only exist in Orchard Core 4.0.
