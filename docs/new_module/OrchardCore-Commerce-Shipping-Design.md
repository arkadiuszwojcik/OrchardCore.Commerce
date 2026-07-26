# OrchardCore Commerce Shipping Module — Design Specification

- **Status:** Proposed implementation design
- **Target:** OrchardCore Commerce 3.x and Orchard Core 3.x
- **Document version:** 2.3
- **Date:** 2026-07-19
- **Primary module:** `OrchardCore.Commerce.Shipping`

> This document describes a provider-neutral shipping subsystem for OrchardCore Commerce. It is intended to be concrete enough to guide implementation while leaving carrier-specific behavior to independent modules such as DHL, DPD, UPS, InPost, Sendcloud, or Shippo.

---

## 1. Executive summary

The proposed subsystem separates the identities and records that shipping integrations commonly conflate:

1. A **shipping provider** is installed integration code, such as DHL, DPD, UPS, InPost, Sendcloud, or Shippo. It is identified by a stable `ProviderKey`.
2. A **provider connection** is a tenant-configured instance of a shipping provider. It contains the environment, account identity, protected credential references, and connection-specific options.
3. A **carrier** is the company that physically transports the parcel. It can differ from the shipping provider when an aggregator is used.
4. A **service** is a provider/carrier product, such as DHL Parcel, DPD Classic, or UPS Express Saver.
5. A **shipping method** is published merchant configuration that exposes a service to customers under store-specific rules, pricing, display text, origin, and packing policy.
6. A **provider rate** is a temporary raw quote returned for one normalized provider request.
7. A **shipping quote** is the merchant-adjusted and tax-resolved checkout option stored server-side for a short lifetime and selected through an opaque token.
8. A **shipment** is the post-order fulfillment aggregate containing allocated order lines, packages, labels, external references, and independent purchase, fulfillment, and tracking states.

The core module owns provider connections, origins, merchant methods, request grouping, packing, quote storage, checkout validation, immutable order snapshots, shipment persistence, authorization, and workflows. Provider modules implement small optional capability interfaces and translate normalized DTOs to external APIs.

The implementation should begin with a checkout/rating kernel. Phase 1 includes physical-data resolution, one explicit origin, provider connections, published shipping methods, grouped provider calls, secure quote storage, tax-resolved charges, checkout selection, and immutable order shipping snapshots. Shipment purchase, labels, tracking, webhooks, and workflows follow only after the kernel is validated with materially different integrations.

---

## 2. Goals

The module should:

- provide a stable public API for direct carriers, aggregators, and built-in non-external rate sources;
- support multiple provider connections for the same shipping provider in one tenant;
- represent the actual carrier separately from the shipping provider when an aggregator is used;
- support multiple services and merchant methods in one checkout response;
- keep provider implementations independent from checkout controllers and order persistence;
- model origins, packages, physical product data, and immutable order-line shipping snapshots explicitly;
- group equivalent provider requests so one external call can serve multiple merchant methods;
- preserve selected shipping charges and operational identities as immutable order snapshots;
- avoid trusting prices, provider identities, service codes, or pickup points posted by the browser;
- support merchant conditions, surcharges, discounts, free shipping, and localized display text without modifying provider modules;
- support optional labels, cancellation, tracking, pickup points, manifests, reconciliation, and webhooks through narrow capability interfaces;
- support multiple packages and partial shipments without redesigning order-line identity later;
- integrate with Orchard Core content items, publishing, localization, shapes, workflows, permissions, indexing, and multi-tenancy;
- provide storefront and administrative APIs over application services rather than serializing `ContentItem` directly;
- degrade gracefully when one provider connection is unavailable;
- declare provider capabilities for administration and early validation while keeping capability interfaces authoritative;
- protect live carrier APIs through connection health checks, bounded concurrency, and tenant/cart/session rate limits;
- define retention, archival, and purge rules for operational history.

---

## 3. Non-goals

The first implementation is not intended to provide:

- warehouse management or stock allocation;
- route optimization;
- freight/LTL quotation;
- a full returns-management system;
- customs brokerage or regulatory advice;
- carrier-account billing reconciliation;
- real-time courier location maps;
- a universal normalized model for every carrier-specific option;
- direct storage of provider credentials inside shipping method content items;
- automatic creation of shipments before an order is successfully placed;
- compatibility guarantees for arbitrary provider payloads persisted as the source of truth.

Carrier-specific data may be retained as limited metadata for diagnostics or later operations, but the normalized core model remains authoritative.

---

## 4. Architectural principles

### 4.1 Small provider capabilities with validated declarations

A shipping provider implements only the operations it supports. Capability interfaces remain the executable source of truth:

```text
IShippingProvider
 ├─ IShippingRateProvider
 ├─ IShippingServiceCatalogProvider
 ├─ IShipmentProvider
 ├─ IShipmentReconciliationProvider     (optional)
 ├─ IShippingLabelProvider
 ├─ IShippingTrackingProvider
 ├─ IPickupPointProvider
 ├─ IShippingManifestProvider           (later phase)
 └─ IShippingWebhookHandler             (optional)
```

A provider descriptor also exposes a declarative capability projection for administration screens, recipe validation, and early configuration checks:

```csharp
[Flags]
public enum ShippingProviderCapabilities
{
    None = 0,
    Rates = 1 << 0,
    ServiceDiscovery = 1 << 1,
    ShipmentCreation = 1 << 2,
    ShipmentCancellation = 1 << 3,
    Labels = 1 << 4,
    Tracking = 1 << 5,
    PickupPoints = 1 << 6,
    Webhooks = 1 << 7,
    Reconciliation = 1 << 8,
    Manifests = 1 << 9,
}
```

The descriptor is metadata, not an alternative execution path. At registration/startup, `ShippingProviderRegistry` validates that declared capabilities match implemented interfaces. A mismatch is a provider-registration error and must be visible in diagnostics.

Static provider capability means “this installed integration can support the operation.” Effective capability may be narrower for a particular provider connection, environment, account entitlement, or service. The core resolves effective capabilities from:

```text
descriptor declaration
    ∩ implemented interfaces
    ∩ provider-connection/environment restrictions
    ∩ optional service-level restrictions
```

A rate-only integration is not forced to implement shipment purchase or tracking. The admin UI can hide unsupported controls before an operation is attempted, while runtime code still resolves and invokes the corresponding interface.

### 4.2 Shipping provider and provider connection are separate

The shipping provider is installed code. The provider connection is tenant-owned configuration.

```text
Shipping provider
    Key: Sendcloud
    Code: SendcloudShippingProvider

Provider connection
    Content item: Company Sendcloud Production
    ProviderKey: Sendcloud
    Environment: Production
    Credential reference: secret-store key

Carrier
    DPD

Service
    DPD Classic
```

A tenant may configure several connections for one provider, for example separate DHL contracts, countries, warehouses, or sandbox and production accounts. Shipping methods reference a provider connection, not only a provider key.

### 4.3 Merchant configuration is separate from integration code

Provider code describes external capabilities. A published `ShippingMethod` decides how one provider connection and service appear and behave in a tenant.

```text
Provider: UPS
Connection: Polish UPS contract
Carrier: UPS

Method A
  Display text: Standard delivery
  Service: UPS_STANDARD
  Countries: PL, DE, CZ
  Markup: +5 PLN

Method B
  Display text: Express delivery
  Service: UPS_EXPRESS_SAVER
  Countries: PL
  Minimum subtotal: 100 PLN
```

### 4.4 Quotes are server-side and transient; order snapshots are permanent

The browser receives an opaque protected quote token. The full `ShippingQuote` is stored in a tenant-scoped short-lived quote store and contains the fingerprint, charge, display values, provider connection, provider/carrier/service identities, origin, delivery estimate, pickup point, and adjustment/tax breakdown.

At submit time, the core loads the quote, validates its fingerprint and expiry, and either honors or re-rates it according to method policy. The accepted values are copied into the order. Historical orders do not depend on current methods, products, provider connections, or external APIs.

Cache loss causes re-rating or a validation response. It never causes the server to trust values posted by the client.

### 4.5 Shipments are fulfillment records, not checkout quotes

Checkout answers which delivery options are available and what the customer will pay. Fulfillment answers which ordered quantities are sent in which packages, through which connection, carrier, and service.

Shipment state is represented through independent dimensions:

- purchase status;
- fulfillment status;
- latest normalized tracking status;
- label references and operation records.

A label is an artifact, not a shipment state. An external-operation failure is an operation result, not one generic aggregate status.

### 4.6 The core owns policy and persistence

The core owns:

- provider connections and secret references;
- origins;
- method publishing, eligibility, and pricing policy;
- physical-data resolution and order snapshots;
- packing and provider-request grouping;
- tax integration;
- quote storage and protection;
- checkout validation;
- shipment and operation persistence;
- authorization, events, and observability.

A provider owns:

- translating normalized requests into external API calls;
- authentication using a resolved provider connection;
- translating services, rates, labels, tracking, and errors;
- signature verification and webhook event translation;
- carrier-specific validation and provider-specific option schemas.

### 4.7 Tenant isolation follows the scope of the namespace

Tenant-resolved Orchard services, content storage, `IMemoryCache`, and tenant-aware distributed-cache implementations are already isolated and do not require an additional tenant prefix in every key.

A stable tenant discriminator must be included or hashed into a key when it enters a namespace that is not tenant-scoped, including:

- host-level or static caches;
- custom shared tables or object stores;
- shared message queues;
- external provider idempotency/reference namespaces;
- provider accounts shared by several Orchard tenants.

---

## 5. Proposed solution and feature structure

Because third-party provider modules are an explicit goal, public contracts should be placed in a small abstractions library.

```text
src/
├─ Libraries/
│  ├─ OrchardCore.Commerce.Abstractions/
│  │  ├─ Constants/
│  │  │  └─ OrderAdditionalCostKinds.cs
│  │  └─ Models/
│  │     └─ OrderLineItem.cs
│  │
│  └─ OrchardCore.Commerce.Shipping.Abstractions/
│     ├─ Providers/
│     ├─ Connections/
│     ├─ Rates/
│     ├─ Packing/
│     ├─ Shipments/
│     ├─ Tracking/
│     └─ ValueObjects/
│
└─ Modules/
   ├─ OrchardCore.Commerce.Shipping/
   │  ├─ Controllers/
   │  ├─ Drivers/
   │  ├─ Events/
   │  ├─ Handlers/
   │  ├─ Indexes/
   │  ├─ Migrations/
   │  ├─ Models/
   │  ├─ Options/
   │  ├─ Services/
   │  ├─ Stores/
   │  ├─ Views/
   │  ├─ Workflows/
   │  ├─ Manifest.cs
   │  └─ Startup.cs
   │
   ├─ OrchardCore.Commerce.Shipping.Dhl/
   ├─ OrchardCore.Commerce.Shipping.Dpd/
   ├─ OrchardCore.Commerce.Shipping.Ups/
   └─ OrchardCore.Commerce.Shipping.InPost/
```

### 5.1 Feature and package identifiers

```text
OrchardCore.Commerce.Shipping.Abstractions
OrchardCore.Commerce.Shipping
OrchardCore.Commerce.Shipping.Api
OrchardCore.Commerce.Shipping.Workflows
```

Provider modules:

```text
OrchardCore.Commerce.Shipping.Dhl
OrchardCore.Commerce.Shipping.Dpd
Kytrixlabs.OrchardCore.Commerce.Shipping.Dhl
Kytrixlabs.OrchardCore.Commerce.Shipping.Dpd
```

### 5.2 Dependency direction

- The abstractions library references only the minimum Orchard/Commerce primitives required by public contracts.
- The core shipping module references the abstractions library and owns content items, stores, application services, UI, and workflows.
- Provider modules reference the abstractions library and the minimum Orchard integration package needed for registration and provider-specific displays.
- Provider modules do not reference checkout controllers, shipment admin controllers, or core persistence implementations.

If project packaging constraints make a separate abstractions library impractical for the first contribution, the same namespaces and dependency boundaries must still be maintained inside `OrchardCore.Commerce.Shipping` so they can be extracted without changing contracts.

---

## 6. Domain terminology

| Term | Meaning |
|---|---|
| Shipping provider | Installed integration code identified by a stable `ProviderKey`, such as `Dhl` or `Sendcloud`. Its descriptor declares potential capabilities, which are validated against implemented interfaces. |
| Provider connection | Tenant-configured provider instance containing environment, account identity, versioned secret-reference metadata, connection-specific options, and health state. |
| Carrier | The company physically transporting a parcel, such as DHL or DPD. It may differ from the provider for aggregator integrations. |
| Service | External product code and metadata, such as `DHL_PARCEL` or `DPD_CLASSIC`. |
| Shipping method | Published merchant configuration that references a provider connection, origin, service selector, packer, conditions, pricing, and customer-facing display text. |
| Provider rate request | Normalized external-rating input containing origin, destination, packages, currency, optional service filter, and provider options. |
| Provider rate | Raw quote returned by a provider before merchant adjustments and tax resolution. |
| Shipping quote | Server-side, short-lived, customer-facing resolved option selected through an opaque token. It carries layered rate, policy, and eligibility fingerprints plus an audit trail. |
| Shipping charge | Net, tax, and gross amounts resulting from method adjustments and shipping tax policy. |
| Package | Physical parcel with weight, dimensions, declared value, and allocated lines. |
| Packer | Strategy that converts shippable lines into one or more packages. |
| Origin | Tenant-configured dispatch or fulfillment location. |
| Order shipping snapshot | Immutable selected quote, origin, pickup point, and per-line physical/customs data stored with the order. |
| Shipment | Persistent fulfillment aggregate for all or part of an order. |
| Shipment operation | Durable record of an external purchase, cancellation, label, tracking, or reconciliation attempt. |
| Label | Printable carrier document associated with a package or shipment. |
| Tracking summary | Bounded latest normalized tracking state on the shipment. Full event history may be stored separately. |
| Pickup point | Provider-managed collection location selected by the customer. |

---

## 7. Core content and persistence model

### 7.1 `ShippingProductPart`

Attach this part to shippable product or variant content types.

```csharp
public sealed class ShippingProductPart : ContentPart
{
    public bool IsShippable { get; set; } = true;
    public decimal? Weight { get; set; }
    public WeightUnit WeightUnit { get; set; } = WeightUnit.Kilogram;
    public decimal? Length { get; set; }
    public decimal? Width { get; set; }
    public decimal? Height { get; set; }
    public DimensionUnit DimensionUnit { get; set; } = DimensionUnit.Millimeter;
    public string? ShippingClass { get; set; }
    public bool ShipsSeparately { get; set; }
    public bool IsHazardous { get; set; }
    public string? CountryOfOrigin { get; set; }
    public string? HarmonizedSystemCode { get; set; }

    // Used only when the selected method explicitly allows overrides.
    public string? PreferredShippingOriginContentItemId { get; set; }
}
```

Physical values are validated and normalized before rating. Non-shippable lines are excluded. A cart containing no shippable lines skips the shipping step.

Phase 1 still produces one origin per provider request. When a method allows product-origin overrides, all shippable lines assigned to that method must resolve to the same origin. If they resolve to more than one origin, the method is unavailable with a structured `MultipleOriginsNotSupported` reason. The core must never silently fall back to the method default origin. Multi-origin splitting is a later-phase feature.

### 7.2 Stable line identifiers

Every new order line must receive a stable identifier during construction or order creation:

```csharp
public required string LineItemId { get; init; }
```

Do not rely on a property initializer that generates a different value whenever an old serialized order without the property is deserialized. New orders persist the identifier when the line is created. Legacy orders use the compatibility strategy below; a bulk rewrite of all historical order JSON is not a prerequisite for enabling the shipping module.

The identifier:

- is unique within the order;
- survives serialization and permitted order edits;
- is not derived only from SKU;
- is copied from a stable cart-line identifier when available;
- is referenced by order shipping snapshots and shipment lines.

#### 7.2.1 Legacy line identity compatibility

Legacy serialized orders are schema-less data and may contain duplicate products, missing attributes, historical model variants, stale product references, reordered JSON properties, or partially malformed lines. The module must not require a risky bulk rewrite of every historical order before shipping can be enabled.

Identity resolution uses this precedence:

1. **Persisted line ID:** return `OrderLineItem.LineItemId` when present.
2. **Persisted legacy mapping:** return a previously stored mapping keyed by order content-item ID, legacy order structure fingerprint, and original line position.
3. **Deterministic synthetic ID:** derive a versioned compatibility ID from values contained in the historical order itself.
4. **Repair required:** block only when the document cannot be parsed, the mapping is ambiguous, or a collision is detected.

The resolver must not query current product content to construct identity. Product titles, SKUs, attributes, or references may have changed since the order was placed.

A versioned canonical payload for one legacy line contains, when available:

```text
order content-item ID
legacy order structure fingerprint
original line position
product content-item ID snapshot
SKU snapshot
quantity
unit price amount and currency
normalized attribute key/value pairs sorted by key and value
historical line type/discriminator
```

The `legacy order structure fingerprint` is calculated from the ordered line array using a canonical serializer. It detects line reordering or mutation between reads. The line position is included as a deterministic tie-breaker for otherwise identical duplicate lines. Because position can change when a legacy order is edited, any edit/split/merge/reorder operation must first persist real IDs or a sidecar mapping.

A compatibility ID is visibly synthetic and versioned:

```text
legacy:v1:{Base32(SHA256(canonical-legacy-line-identity))}
```

The resolver returns metadata rather than only a string:

```csharp
public sealed record OrderLineIdentity
{
    public required string LineItemId { get; init; }
    public required bool IsSynthetic { get; init; }
    public required string AlgorithmVersion { get; init; }
    public required string OrderStructureFingerprint { get; init; }
    public required int OriginalLineIndex { get; init; }
}
```

Synthetic IDs may be used for historical fulfillment when:

- the entire order-line array can be parsed;
- the current order structure fingerprint matches the mapping context;
- one unique identity is produced per line;
- no persisted shipment allocation conflicts with the generated mapping;
- the mapping and algorithm version are captured in shipment operations and audit data.

Two persistence options are allowed:

- **in-place upgrade:** save real `LineItemId` values into the historical order when the order can be safely validated and saved;
- **sidecar mapping:** persist a tenant-scoped `LegacyOrderLineIdentityMap` when historical content must remain unchanged or cannot be republished.

```csharp
public sealed record LegacyOrderLineIdentityMap
{
    public required string OrderContentItemId { get; init; }
    public required string OrderStructureFingerprint { get; init; }
    public required string AlgorithmVersion { get; init; }
    public IReadOnlyList<LegacyOrderLineIdentityEntry> Lines { get; init; } = [];
}
```

The administration UI must support:

1. **Dry run:** report readable, automatically resolvable, ambiguous, and corrupt orders before writes.
2. **Persist mapping:** save generated IDs/mappings for selected orders or restartable batches.
3. **Repair:** show original line order and proposed identities, allow manual assignment, and preserve an audit record.

A legacy order is blocked from shipment only when identity cannot be resolved deterministically or safely. Section 31.1 defines batching, checkpoints, collision detection, recovery, and compatibility guarantees.

### 7.3 `ShippingProviderConnection` content item

Create a `ShippingProviderConnection` content type with `TitlePart` and `ShippingProviderConnectionPart`.

```csharp
public sealed class ShippingProviderConnectionPart : ContentPart
{
    public bool Enabled { get; set; } = true;
    public string ProviderKey { get; set; } = string.Empty;
    public string Environment { get; set; } = string.Empty;

    public string CredentialReference { get; set; } = string.Empty;
    public int CredentialVersion { get; set; } = 1;
    public DateTime? CredentialLastRotatedUtc { get; set; }
    public bool CredentialRotationRequired { get; set; }

    public int ConfigurationRevision { get; set; }
    public ShippingExtensionConfiguration Configuration { get; set; } = new();
}
```

`CredentialReference` points to a protected tenant secret store. It is not a credential. `CredentialVersion` is audit/concurrency metadata for the active secret set; historical secret values are not copied into content items, orders, quotes, or operation records.

Provider-specific non-secret settings use a versioned extension envelope:

```csharp
public sealed record ShippingExtensionConfiguration
{
    public string Type { get; init; } = string.Empty;
    public int Version { get; init; } = 1;
    public JsonObject Settings { get; init; } = new();
}
```

Connection behavior:

- checkout and shipment operations use enabled, published connections;
- drafts do not affect active checkout;
- publishing a non-secret semantic change increments `ConfigurationRevision`;
- successful secret rotation atomically advances `CredentialVersion` and `CredentialLastRotatedUtc`;
- secret rotation does not automatically invalidate raw-rate cache entries unless provider/account semantics materially changed;
- a missing, disabled, or unreadable secret reference makes the connection unhealthy and suppresses its methods with an actionable administration error;
- sandbox and production are separate connection configurations even when they share one provider implementation;
- referenced connections are disabled/archived rather than hard-deleted;
- historical order snapshots retain provider/connection identifiers but never secret values.

A provider-connection health record is stored separately because health is transient operational state, not versioned editorial content.

### 7.4 `ShippingOrigin` content item

Create a `ShippingOrigin` content type with `TitlePart` and `ShippingOriginPart`.

```csharp
public sealed class ShippingOriginPart : ContentPart
{
    public bool Enabled { get; set; } = true;
    public AddressSnapshot Address { get; set; } = new();
    public string? ContactName { get; set; }
    public string? Company { get; set; }
    public string? Phone { get; set; }
    public string? Email { get; set; }
    public int ConfigurationRevision { get; set; }
    public IList<string> PackageTypeCodes { get; } = [];
}
```

Phase 1 supports one selected origin per method/request. Multi-origin allocation is deferred, but the origin entity is not. The exact origin address and contact data used at checkout are snapshotted into the order and shipment.

### 7.5 `ShippingMethod` content item

Create a versionable and localizable `ShippingMethod` content type with `TitlePart` and `ShippingMethodPart`.

```csharp
public sealed class ShippingMethodPart : ContentPart
{
    public bool Enabled { get; set; } = true;
    public string ProviderConnectionContentItemId { get; set; } = string.Empty;
    public string OriginContentItemId { get; set; } = string.Empty;
    public bool AllowProductOriginOverride { get; set; }
    public string? CarrierCode { get; set; }
    public string ServiceCode { get; set; } = string.Empty;
    public string PackerKey { get; set; } = ShippingPackerKeys.SinglePackage;
    public int SortOrder { get; set; }
    public int QuoteLifetimeSeconds { get; set; } = 300;
    public ShippingQuoteValidationMode QuoteValidationMode { get; set; }
        = ShippingQuoteValidationMode.RequoteOnSubmit;
    public int PolicyRevision { get; set; }
    public ShippingExtensionConfiguration ProviderRateOptions { get; set; } = new();
    public IList<ShippingExtensionConfiguration> Conditions { get; } = [];
    public IList<ShippingExtensionConfiguration> Adjustments { get; } = [];
}
```

Lifecycle rules:

- checkout queries published methods only;
- drafts do not affect checkout;
- publishing a semantic change increments `PolicyRevision`;
- deletion is discouraged when referenced; disabling/archiving is preferred;
- historical orders display snapshots and do not require the current method;
- when `AllowProductOriginOverride` is false, `OriginContentItemId` is authoritative;
- when it is true, all shippable lines must resolve to one effective origin in Phase 1; otherwise the method is unavailable rather than silently misquoted.

`TitlePart.DisplayText` is the merchant-defined customer label. Operational behavior uses stable IDs and codes, never display text.

### 7.6 `ShippingOrderPart`

Attach a dedicated shipping part to `Order`.

```csharp
public sealed class ShippingOrderPart : ContentPart
{
    public SelectedShippingQuoteSnapshot? SelectedQuote { get; set; }
    public AddressSnapshot? Origin { get; set; }
    public PickupPointSnapshot? PickupPoint { get; set; }
    public IList<OrderShippingLineSnapshot> Lines { get; } = [];
}
```

Do not store a reverse `ShipmentContentItemIds` list. Shipments are queried through an index on `ShipmentPart.OrderContentItemId`.

Display values use one explicit hierarchy:

```csharp
public sealed record ShippingDisplayInfo
{
    public required string CustomerLabel { get; init; }
    public string? ProviderServiceLabel { get; init; }
    public string? CarrierLabel { get; init; }
    public string? ProviderConnectionLabel { get; init; }
}
```

```csharp
public sealed record SelectedShippingQuoteSnapshot
{
    public required string ShippingMethodContentItemId { get; init; }
    public required ShippingDisplayInfo DisplayInfo { get; init; }

    public required string RateFingerprint { get; init; }
    public required string PolicyFingerprint { get; init; }
    public required string EligibilityFingerprint { get; init; }

    public required string ProviderKey { get; init; }
    public required string ProviderConnectionContentItemId { get; init; }
    public string? CarrierCode { get; init; }
    public required string ServiceCode { get; init; }
    public required ShippingCharge Charge { get; init; }
    public Amount? ProviderAmount { get; init; }
    public required DateTime QuotedAtUtc { get; init; }
    public DateTime? EstimatedDeliveryFromUtc { get; init; }
    public DateTime? EstimatedDeliveryToUtc { get; init; }
    public string? ProviderQuoteId { get; init; }
    public IReadOnlyList<ShippingAdjustmentSnapshot> Adjustments { get; init; } = [];
    public IReadOnlyList<ShippingQuoteAuditEntry> AuditEntries { get; init; } = [];
    public ShippingExtensionData ExtensionData { get; init; } = ShippingExtensionData.Empty;
}
```

`CustomerLabel` is copied once from `ShippingMethod.TitlePart.DisplayText`. The accepted layered fingerprints and bounded quote audit are copied into the order because the temporary quote may be removed after consumption. Provider operations use stable IDs/codes; labels are historical/UI data only.

Each order line receives an immutable physical/customs snapshot generated from the same resolved data used for rating:

```csharp
public sealed record OrderShippingLineSnapshot
{
    public required string LineItemId { get; init; }
    public required bool IsShippable { get; init; }
    public Weight? UnitWeight { get; init; }
    public Dimensions? UnitDimensions { get; init; }
    public string? ShippingClass { get; init; }
    public bool ShipsSeparately { get; init; }
    public bool IsHazardous { get; init; }
    public string? CountryOfOrigin { get; init; }
    public string? HarmonizedSystemCode { get; init; }
    public string? ResolvedOriginContentItemId { get; init; }
}
```

### 7.7 Shipping charge and `OrderAdditionalCost`

Shipping tax treatment is explicit:

```csharp
public enum ShippingTaxMode
{
    Inclusive,  // Customer-facing price already includes tax.
    Exclusive,  // Tax is calculated and added to the net amount.
    ZeroRated,  // Taxable supply with a 0% rate.
    Exempt,     // Not taxable under the selected tax rule.
}
```

Regardless of mode, the resolved charge always carries explicit net, tax, and gross amounts. `ZeroRated` and `Exempt` both normally have a zero tax amount, but remain distinct for reporting and legal semantics.

The resolved customer charge is explicit:

```csharp
public sealed record ShippingCharge
{
    public required Amount NetAmount { get; init; }
    public required Amount TaxAmount { get; init; }
    public required Amount GrossAmount { get; init; }
    public required ShippingTaxMode TaxMode { get; init; }
}
```

The shipping module writes exactly one additional cost using the final gross amount:

```csharp
orderPart.AdditionalCosts.Add(new OrderAdditionalCost
{
    Kind = OrderAdditionalCostKinds.Shipping,
    Description = selectedQuote.DisplayInfo.CustomerLabel,
    Cost = selectedQuote.Charge.GrossAmount,
});
```

The tax adapter must mark this charge as already tax-resolved for order-total purposes. It may expose the net/tax breakdown but must not add the tax a second time. Phase 1 is incomplete until the Commerce tax integration honors this invariant.

#### 7.7.1 Tax integration compatibility gate and concrete contract

This is a hard cross-module dependency and must be proven before checkout contracts are frozen. The shipping module must not assume that `OrderAdditionalCost` is automatically treated as tax-resolved.

The preferred shared Commerce abstraction is:

```csharp
public interface IOrderAdditionalCostTaxResolver
{
    ValueTask<OrderAdditionalCostTaxResult?> ResolveAsync(
        OrderAdditionalCost cost,
        OrderAdditionalCostTaxContext context,
        CancellationToken cancellationToken);
}

public sealed record OrderAdditionalCostTaxResult
{
    public required bool IsAlreadyResolved { get; init; }
    public Amount? NetAmount { get; init; }
    public Amount? TaxAmount { get; init; }
    public required Amount GrossAmount { get; init; }
    public string? TaxCategory { get; init; }
}
```

The shipping module writes typed metadata alongside the additional cost:

```csharp
public sealed record ShippingAdditionalCostTaxDetails
{
    public required Amount NetAmount { get; init; }
    public required Amount TaxAmount { get; init; }
    public required Amount GrossAmount { get; init; }
    public required ShippingTaxMode TaxMode { get; init; }
    public string? TaxCategory { get; init; }
}
```

The shipping resolver returns `IsAlreadyResolved = true`, allowing totals to expose the breakdown without adding tax again.

Before Phase 1 proceeds beyond a prototype, create a focused compatibility spike that:

1. traces current cart/order total and tax-provider execution;
2. verifies how `OrderAdditionalCost` is currently taxed or ignored;
3. implements the smallest shared hook required for the contract above;
4. proves taxable products plus taxable shipping do not double-count tax;
5. proves zero-rated, tax-inclusive, tax-exclusive, and tax-exempt shipping cases;
6. records the accepted API in an ADR and integration tests.

If the current tax module cannot support this contract without a broad refactor, revise the shipping persistence model before public release. Do not compensate by subtracting duplicated tax later.

### 7.8 `Shipment` content item

Create a `Shipment` content type with `ShipmentPart`.

```csharp
public sealed class ShipmentPart : ContentPart
{
    public string ShipmentId { get; set; } = IdGenerator.GenerateId();
    public string OrderContentItemId { get; set; } = string.Empty;
    public string? OrderNumberSnapshot { get; set; }

    public string ProviderKey { get; set; } = string.Empty;
    public string ProviderConnectionContentItemId { get; set; } = string.Empty;
    public string? CarrierCode { get; set; }
    public string ServiceCode { get; set; } = string.Empty;

    public ShipmentPurchaseStatus PurchaseStatus { get; set; } = ShipmentPurchaseStatus.Draft;
    public ShipmentFulfillmentStatus FulfillmentStatus { get; set; } = ShipmentFulfillmentStatus.Unshipped;
    public ShipmentTrackingStatus TrackingStatus { get; set; } = ShipmentTrackingStatus.Unknown;

    public AddressSnapshot Origin { get; set; } = new();
    public AddressSnapshot Destination { get; set; } = new();
    public PickupPointSnapshot? PickupPoint { get; set; }
    public IList<ShipmentLine> Lines { get; } = [];
    public IList<ShipmentPackage> Packages { get; } = [];
    public IList<ShipmentLabelReference> Labels { get; } = [];
    public IList<TrackingReference> TrackingReferences { get; } = [];
    public TrackingSummary? LatestTracking { get; set; }

    public string? ProviderShipmentId { get; set; }
    public string? ProviderCorrelationId { get; set; }
    public DateTime CreatedUtc { get; set; }
    public DateTime? PurchasedUtc { get; set; }
    public DateTime? ShippedUtc { get; set; }
    public DateTime? DeliveredUtc { get; set; }
    public ShippingExtensionData ExtensionData { get; set; } = ShippingExtensionData.Empty;
}
```

`OrderContentItemId` is the authoritative relationship. `OrderNumberSnapshot` is optional historical/display data.

### 7.9 Shipment operations and tracking history

External operations are durable records stored separately from shipment content JSON:

```csharp
public sealed class ShipmentOperation
{
    public string OperationId { get; set; } = IdGenerator.GenerateId();
    public string ShipmentId { get; set; } = string.Empty;
    public ShipmentOperationType Type { get; set; }
    public ShipmentOperationStatus Status { get; set; }
    public int Generation { get; set; }
    public string IdempotencyKey { get; set; } = string.Empty;
    public string RequestFingerprint { get; set; } = string.Empty;
    public int CredentialVersion { get; set; }
    public DateTime StartedUtc { get; set; }
    public DateTime? CompletedUtc { get; set; }
    public DateTime? ArchiveAfterUtc { get; set; }
    public DateTime? PurgeAfterUtc { get; set; }
    public bool LegalHold { get; set; }
    public bool ReconciliationRequired { get; set; }
    public ShippingError? Error { get; set; }
    public ShippingExtensionData ExtensionData { get; set; } = ShippingExtensionData.Empty;
}
```

Full tracking history, webhook deduplication, and quote audit records also use separate stores. Retention is configurable and enforced by a maintenance task:

- successful operations: default 90 days online, then archive;
- failed operations: default one year;
- tracking events: default 30 days after delivery;
- reconciliation-required, disputed, or legal-hold records: retained until released;
- compact order-history summaries are preserved.

The defaults are policy examples, not legal advice. Tenant settings may require longer retention.

### 7.10 Labels

Large binary labels are stored outside content-item JSON through `IShippingDocumentStore`. References contain MIME type, checksum, size, package reference, and protected storage identifier. Raw storage paths are never exposed as public URLs.

---

## 8. Value objects and normalized request models

### 8.1 Validated physical and monetary values

Value objects must reject negative values and unknown units at construction or through factories.

```csharp
public readonly record struct Weight
{
    public decimal Value { get; }
    public WeightUnit Unit { get; }

    public Weight(decimal value, WeightUnit unit)
    {
        if (value < 0) throw new ArgumentOutOfRangeException(nameof(value));
        if (!Enum.IsDefined(unit)) throw new ArgumentOutOfRangeException(nameof(unit));
        Value = value;
        Unit = unit;
    }
}

public readonly record struct Dimensions
{
    public decimal Length { get; }
    public decimal Width { get; }
    public decimal Height { get; }
    public DimensionUnit Unit { get; }

    public Dimensions(decimal length, decimal width, decimal height, DimensionUnit unit)
    {
        if (length < 0 || width < 0 || height < 0)
            throw new ArgumentOutOfRangeException(nameof(length));
        if (!Enum.IsDefined(unit)) throw new ArgumentOutOfRangeException(nameof(unit));
        Length = length;
        Width = width;
        Height = height;
        Unit = unit;
    }
}
```

Use `Amount` for declared values instead of a separate decimal and currency pair.

### 8.2 Address

Provider contracts receive immutable normalized address snapshots. Country codes are ISO 3166-1 alpha-2 uppercase values; currency codes are ISO 4217 uppercase values.

```csharp
public sealed record ShippingAddress
{
    public string? Company { get; init; }
    public string? FirstName { get; init; }
    public string? LastName { get; init; }
    public string AddressLine1 { get; init; } = string.Empty;
    public string? AddressLine2 { get; init; }
    public string City { get; init; } = string.Empty;
    public string? AdministrativeArea { get; init; }
    public string PostalCode { get; init; } = string.Empty;
    public string CountryCode { get; init; } = string.Empty;
    public string? Phone { get; init; }
    public string? Email { get; init; }
}
```

### 8.3 Core checkout context versus provider request

Orchard and customer identifiers remain in a core context:

```csharp
public sealed record ShippingCheckoutContext
{
    public required string CartId { get; init; }
    public string? CustomerId { get; init; }
    public required ShippingAddress Destination { get; init; }
    public required string Currency { get; init; }
    public required IReadOnlyList<CheckoutLine> Lines { get; init; }
}
```

The provider receives only information needed for the external operation:

```csharp
public sealed record ProviderRateRequest
{
    public required string CorrelationId { get; init; }
    public required string ProviderConnectionContentItemId { get; init; }
    public required ShippingAddress Origin { get; init; }
    public required ShippingAddress Destination { get; init; }
    public required IReadOnlyList<ShippingPackage> Packages { get; init; }
    public required string Currency { get; init; }
    public string? CarrierCode { get; init; }
    public string? ServiceCode { get; init; }
    public PickupPoint? PickupPoint { get; init; }
    public ShippingExtensionConfiguration Options { get; init; } = new();
}
```

Do not pass `ContentItem`, `HttpContext`, mutable cart objects, method content-item IDs, or customer IDs to provider capabilities.

### 8.4 Package and lines

```csharp
public sealed record ShippingPackage
{
    public required string PackageId { get; init; }
    public required Weight Weight { get; init; }
    public required Dimensions Dimensions { get; init; }
    public string? PackageTypeCode { get; init; }
    public Amount? DeclaredValue { get; init; }
    public IReadOnlyList<ShippingPackageLine> Lines { get; init; } = [];
    public ShippingExtensionData ExtensionData { get; init; } = ShippingExtensionData.Empty;
}

public sealed record ShippingPackageLine
{
    public required string LineItemId { get; init; }
    public required string Sku { get; init; }
    public required int Quantity { get; init; }
    public required Weight UnitWeight { get; init; }
    public Dimensions? UnitDimensions { get; init; }
    public Amount? UnitValue { get; init; }
    public string? CountryOfOrigin { get; init; }
    public string? HarmonizedSystemCode { get; init; }
}
```

### 8.5 Provider rate

```csharp
public sealed record ProviderShippingRate
{
    public required string ProviderKey { get; init; }
    public required string ProviderConnectionContentItemId { get; init; }
    public string? CarrierCode { get; init; }
    public string? CarrierLabel { get; init; }
    public required string ServiceCode { get; init; }
    public string? ProviderServiceLabel { get; init; }
    public required Amount Amount { get; init; }
    public string? QuoteId { get; init; }
    public DateTime? ExpiresUtc { get; init; }
    public DeliveryEstimate? DeliveryEstimate { get; init; }
    public bool RequiresPickupPoint { get; init; }
    public ShippingExtensionData ExtensionData { get; init; } = ShippingExtensionData.Empty;
}
```

Capabilities are resolved from descriptor metadata, implemented interfaces, connection restrictions, and service metadata. They are not copied as unrelated booleans into every rate.

### 8.6 Server-side shipping quote

```csharp
public sealed record ShippingQuote
{
    public required string QuoteId { get; init; }

    public required string RateFingerprint { get; init; }
    public required string PolicyFingerprint { get; init; }
    public required string EligibilityFingerprint { get; init; }

    public required string ShippingMethodContentItemId { get; init; }
    public required int ShippingMethodPolicyRevision { get; init; }
    public required ShippingDisplayInfo DisplayInfo { get; init; }

    public required string ProviderKey { get; init; }
    public required string ProviderConnectionContentItemId { get; init; }
    public string? CarrierCode { get; init; }
    public required string ServiceCode { get; init; }

    public required AddressSnapshot Origin { get; init; }
    public required ShippingCharge Charge { get; init; }
    public required Amount ProviderAmount { get; init; }
    public string? ProviderQuoteId { get; init; }
    public DeliveryEstimate? DeliveryEstimate { get; init; }
    public PickupPointSnapshot? PickupPoint { get; init; }
    public IReadOnlyList<ShippingAdjustmentSnapshot> Adjustments { get; init; } = [];
    public IReadOnlyList<ShippingQuoteAuditEntry> AuditEntries { get; init; } = [];
    public required DateTime IssuedUtc { get; init; }
    public required DateTime ExpiresUtc { get; init; }
    public ShippingExtensionData ExtensionData { get; init; } = ShippingExtensionData.Empty;
}

public sealed record ShippingQuoteAuditEntry
{
    public required DateTime TimestampUtc { get; init; }
    public required string EventType { get; init; }
    public string? ReasonCode { get; init; }
    public Amount? PreviousAmount { get; init; }
    public Amount? NewAmount { get; init; }
    public string? CorrelationId { get; init; }
}
```

Audit event types include `QuoteCreated`, `Revalidated`, `Repriced`, `Expired`, `Rejected`, and `Consumed`. The audit trail excludes credentials, raw provider payloads, and unnecessary personal data.

The storefront DTO contains safe display fields and an opaque token, not the authoritative quote.

## 9. Provider contracts

### 9.1 Base provider

```csharp
public interface IShippingProvider
{
    string ProviderKey { get; }
    ShippingProviderDescriptor Descriptor { get; }
}

public sealed record ShippingProviderDescriptor
{
    public required LocalizedString DisplayName { get; init; }
    public required ShippingProviderCapabilities Capabilities { get; init; }
    public bool SupportsSandbox { get; init; }
    public string ImplementationVersion { get; init; } = "1";
    public Uri? DocumentationUri { get; init; }
}
```

`ProviderKey` is a stable, non-localized, case-insensitively unique machine identifier. `ImplementationVersion` participates in raw-rate cache policy when request/response semantics change.

`ShippingProviderRegistry` validates descriptor declarations against implemented interfaces and rejects or quarantines mismatches.

### 9.2 Provider connection resolution

Provider capabilities receive a resolved immutable connection context:

```csharp
public sealed record ShippingProviderConnectionContext
{
    public required string ContentItemId { get; init; }
    public required string ProviderKey { get; init; }
    public required string Environment { get; init; }
    public required int ConfigurationRevision { get; init; }
    public required int CredentialVersion { get; init; }
    public required IReadOnlyDictionary<string, string> Secrets { get; init; }
    public required ShippingExtensionConfiguration Configuration { get; init; }
}
```

Secrets are materialized only for the provider operation and never persisted into quotes, orders, shipments, logs, events, or cache keys.

```csharp
public enum ShippingConnectionHealthStatus
{
    Unknown,
    Healthy,
    Degraded,
    Unhealthy,
    CredentialIssue,
    ConfigurationIssue,
    TemporaryUnavailable,
}

public interface IShippingProviderConnectionHealthService
{
    Task<ShippingProviderConnectionHealth> GetAsync(
        string providerConnectionContentItemId,
        bool forceRefresh,
        CancellationToken cancellationToken);
}

public sealed record ShippingProviderConnectionHealth
{
    public required bool IsHealthy { get; init; }
    public required ShippingConnectionHealthStatus Status { get; init; }
    public string? SafeMessage { get; init; }
    public required DateTime CheckedUtc { get; init; }
    public required DateTime ExpiresUtc { get; init; }
}
```

Health checks are cached and bounded. Checkout uses recent health plus the actual rate call; it does not run an expensive diagnostic call on every request. `Healthy` and an explicitly allowed `Degraded` result may remain usable; `CredentialIssue`, `ConfigurationIssue`, `Unhealthy`, and `TemporaryUnavailable` suppress only that connection's methods. `Unknown` triggers bounded refresh or conservative unavailability according to tenant policy.

### 9.3 Rate capability

```csharp
public interface IShippingRateProvider : IShippingProvider
{
    Task<IReadOnlyList<ProviderShippingRate>> GetRatesAsync(
        ShippingProviderConnectionContext connection,
        ProviderRateRequest request,
        CancellationToken cancellationToken);
}
```

Rules:

- an empty list means no available services;
- methods honor cancellation and provider timeouts;
- returned provider key and connection ID must match the resolved context;
- providers do not persist orders, quotes, or shipments;
- retryability and safe error categories use the normalized error model.

### 9.4 Service discovery capability

```csharp
public interface IShippingServiceCatalogProvider : IShippingProvider
{
    Task<IReadOnlyList<ShippingServiceDescriptor>> GetServicesAsync(
        ShippingProviderConnectionContext connection,
        ShippingServiceDiscoveryContext context,
        CancellationToken cancellationToken);
}
```

Discovery may depend on account entitlements, environment, origin, destination region, or currency. It is an admin convenience and is not required for providers whose service codes are entered manually.

### 9.5 Shipment purchase and cancellation

```csharp
public interface IShipmentProvider : IShippingProvider
{
    Task<CreateShipmentResult> CreateShipmentAsync(
        ShippingProviderConnectionContext connection,
        CreateShipmentRequest request,
        CancellationToken cancellationToken);

    Task<CancelShipmentResult> CancelShipmentAsync(
        ShippingProviderConnectionContext connection,
        CancelShipmentRequest request,
        CancellationToken cancellationToken);
}
```

Requests contain normalized immutable snapshots, an operation ID, merchant reference, idempotency key, selected carrier/service, packages, and validated provider options. They do not contain mutable Orchard content items.

### 9.6 Reconciliation capability

```csharp
public interface IShipmentReconciliationProvider : IShippingProvider
{
    Task<ExternalShipmentLookupResult> FindByMerchantReferenceAsync(
        ShippingProviderConnectionContext connection,
        FindExternalShipmentRequest request,
        CancellationToken cancellationToken);
}
```

This capability is optional but required before automatic retries can claim robust duplicate prevention for providers without native idempotency. The core guarantees at-least-once execution with deduplication/reconciliation, not universal exactly-once external behavior.

### 9.7 Label, tracking, pickup-point, and webhook capabilities

All capability methods receive the resolved provider connection context.

```csharp
public interface IShippingLabelProvider : IShippingProvider
{
    Task<IReadOnlyList<ShippingDocument>> GetLabelsAsync(
        ShippingProviderConnectionContext connection,
        GetShippingLabelsRequest request,
        CancellationToken cancellationToken);
}

public interface IShippingTrackingProvider : IShippingProvider
{
    Task<TrackingInformation> TrackAsync(
        ShippingProviderConnectionContext connection,
        TrackingRequest request,
        CancellationToken cancellationToken);
}

public interface IPickupPointProvider : IShippingProvider
{
    Task<IReadOnlyList<PickupPoint>> FindPickupPointsAsync(
        ShippingProviderConnectionContext connection,
        PickupPointSearchRequest request,
        CancellationToken cancellationToken);

    Task<PickupPoint?> GetPickupPointAsync(
        ShippingProviderConnectionContext connection,
        string pickupPointId,
        CancellationToken cancellationToken);
}

public interface IShippingWebhookHandler : IShippingProvider
{
    Task<ShippingWebhookResult> HandleAsync(
        ShippingProviderConnectionContext connection,
        ShippingWebhookRequest request,
        CancellationToken cancellationToken);
}
```

For aggregator integrations, normalized tracking, label, and shipment results preserve both provider and actual carrier identities where available.

### 9.8 Provider registry and connection store

```csharp
public interface IShippingProviderRegistry
{
    IReadOnlyCollection<IShippingProvider> GetAll();
    IShippingProvider? Find(string providerKey);
    TCapability? Find<TCapability>(string providerKey)
        where TCapability : class, IShippingProvider;
}

public interface IShippingProviderConnectionStore
{
    Task<ShippingProviderConnectionContext?> GetEnabledAsync(
        string contentItemId,
        CancellationToken cancellationToken);
}
```

Registration:

```csharp
services.AddShippingProvider<DhlShippingProvider>();
```

Provider instances are normally scoped. Provider HTTP clients use a shared creation abstraction while each provider module owns endpoint-specific serialization and authentication:

```csharp
public interface IShippingProviderHttpClientFactory
{
    HttpClient CreateClient(
        string providerKey,
        string providerConnectionContentItemId);
}
```

The factory supplies connection pooling, DNS refresh, bounded timeout, telemetry, circuit breaking, and safe retry behavior. Resilience must distinguish reads/idempotent operations from mutating operations:

- rate, service-discovery, health, and tracking reads may retry transient network failures and `429`/selected `5xx` responses with jitter and `Retry-After` support;
- shipment purchase or cancellation is retried automatically only when the provider operation is demonstrably idempotent and reuses the persisted operation key;
- non-idempotent writes never receive a blind generic retry policy;
- provider-specific timeouts may be stricter than the tenant default but must remain bounded;
- circuit state and metrics are partitioned by provider key and, where needed, provider connection so one unhealthy account does not block unrelated accounts.

Provider modules may wrap this factory in typed clients. They must not create unmanaged `HttpClient` instances per request.

---

## 10. Packing subsystem

### 10.1 Contract

```csharp
public interface IPackagePacker
{
    string Key { get; }

    Task<PackagePackingResult> PackAsync(
        PackagePackingRequest request,
        CancellationToken cancellationToken);
}
```

```csharp
public sealed record PackagePackingRequest
{
    public required IReadOnlyList<ShippableLine> Lines { get; init; }
    public required ShippingAddress Origin { get; init; }
    public required ShippingAddress Destination { get; init; }
    public required string Currency { get; init; }
    public IReadOnlyList<ShippingPackageType> AvailablePackageTypes { get; init; } = [];
    public ShippingExtensionConfiguration Options { get; init; } = new();
}
```

### 10.2 Built-in packers

MVP:

- `SinglePackagePacker`: combines all shippable lines into one package, unless a line is marked `ShipsSeparately`;
- merchant-configured standard store box sizes used by the single-package packer;
- explicit maximum package weight and dimensions;
- an optional weight-only mode for methods/providers that do not require dimensions.

Later:

- `EachItemPacker`;
- full three-dimensional `VolumeBasedPacker`/bin packing;
- `WeightLimitPacker`;
- `ShippingClassPacker`;
- `WarehousePacker`;
- custom third-party packers.

### 10.3 Single-package calculation

For MVP, packed weight is the sum of unit weight multiplied by quantity plus packaging tare weight.

The default packer must consider individual item dimensions as well as aggregate volume. A volume-only test can select a box that is large enough in cubic volume but too short for a long item.

The bounded standard-box algorithm is:

1. Normalize all dimensions and generate allowed axis-aligned orientations for each item.
2. Calculate total item volume and apply an explicit packing-factor allowance.
3. For each configured box, verify that every individual item has at least one orientation that fits within the box's inner dimensions.
4. Reject boxes whose maximum weight is below product weight plus tare.
5. Reject boxes whose inner volume is below adjusted total volume.
6. Reject boxes or final packages that exceed method/provider hard limits.
7. Select the smallest eligible box by outer volume, then tare weight and configured priority.
8. Submit the selected box's external dimensions and final packed weight.

```csharp
var candidates = boxes
    .Where(box => lines.All(line =>
        FitsInAnyOrientation(line.UnitDimensions, box.InnerDimensions)))
    .Where(box => box.MaximumWeight is null ||
        productWeight + box.EmptyWeight <= box.MaximumWeight)
    .Where(box => box.InnerDimensions.Volume >= adjustedItemVolume)
    .Where(WithinMethodAndProviderLimits)
    .OrderBy(box => box.OuterDimensions.Volume)
    .ThenBy(box => box.EmptyWeight)
    .ThenBy(box => box.SortOrder);
```

This is a heuristic, not full 3D bin packing. It prevents obviously impossible packages and severe dimensional-weight distortions.

Safety behavior:

- no dimension is silently clamped or reduced;
- no-fit returns structured `PackingFailed`;
- splitting requires an explicitly selected splitting packer;
- dimensional methods are unavailable without store boxes or explicit fallback dimensions;
- weight-only mode is allowed only when the provider contract permits it;
- tests include long-thin, flat, rotated, duplicate-quantity, and volume-pass/dimension-fail cases.

### 10.4 Package types

For the MVP, `ShippingPackageType` is tenant settings data, not an Orchard content type. Package types are few, loaded together during packing, and do not normally require publishing, routing, ownership, or per-item permissions.

```csharp
public sealed record ShippingPackageType
{
    public required string Code { get; init; }
    public required string DisplayText { get; init; }
    public required Dimensions InnerDimensions { get; init; }
    public required Dimensions OuterDimensions { get; init; }
    public required Weight EmptyWeight { get; init; }
    public Weight? MaximumWeight { get; init; }
    public bool Enabled { get; init; } = true;
    public int SortOrder { get; init; }
}
```

The actual `ShippingPackage` used in a quote or shipment is an immutable snapshot and never depends on later package-type edits.

Promotion to a content type is deferred until localization, Orchard fields, origin-specific relationships, media, workflows, or independently permissioned editing are required.

## 11. Merchant eligibility and pricing pipeline

### 11.1 Two-stage grouped pipeline

The core must not call a provider once per merchant method when several methods can share the same external request.

Stage A — compile merchant candidates:

1. Apply checkout endpoint rate limits.
2. Load enabled, published methods.
3. Resolve provider connection and effective capability metadata.
4. Resolve one effective origin per candidate, including allowed product overrides.
5. Reject candidates requiring unsupported capabilities, unhealthy connections, or multiple origins in Phase 1.
6. Evaluate merchant conditions.
7. Resolve and normalize physical data.
8. Pack lines.
9. Build the normalized provider-rate request.

Stage B — group and resolve provider requests:

1. Group equivalent requests by provider connection, effective origin snapshot, destination, packages, currency, pickup point, and provider-rate options.
2. Read one cached provider result or call the provider once per unique group with bounded concurrency and single-flight protection.
3. Fan returned carrier/service rates out to matching methods.
4. Filter by carrier/service selector.
5. Apply adjustments.
6. Apply shipping tax.
7. Store a short-lived `ShippingQuote` with layered fingerprints and audit entries.
8. Return safe display data plus an opaque token.

A failure or unhealthy connection is isolated to its group. Other providers and built-in methods remain available.

### 11.2 Provider request equivalence and cache key

A provider-request fingerprint includes normalized:

- provider connection content-item ID and configuration revision;
- origin content-item ID, revision, and snapshot;
- destination;
- package lines, dimensions, weights, declared values, and package types;
- currency;
- pickup point when it changes the provider request;
- versioned provider-rate options.

It does not include shipping method identity unless that method changes the actual provider request.

Fingerprinting must operate on the exact canonical request DTO sent to the provider, not on raw product fields. Physical-data normalization is therefore a correctness requirement, not only a cache optimization:

- use `decimal` or integral minor units; never use binary floating-point values in canonical DTOs;
- convert weights and dimensions to one canonical unit before rounding, packing, grouping, or hashing;
- apply an explicit normalization profile with deterministic precision and midpoint rounding;
- normalize address casing/whitespace only where the provider semantics allow it;
- sort packages, lines, options, and JSON properties deterministically;
- serialize using invariant culture and a versioned canonical format;
- include the normalization-profile version in the fingerprint.

Example defaults may round weight to `0.01 kg` and dimensions to `0.1 cm` or their exact integral equivalents. The actual precision must be chosen deliberately for the canonical unit and provider requirements; it must not vary with the source unit. Thus `1.00001 kg` and `1.0 kg` can group together under the same profile, while a materially different package remains distinct.

```csharp
public sealed record ShippingNormalizationProfile
{
    public required string Version { get; init; }
    public required Weight WeightIncrement { get; init; }
    public required DimensionUnit CanonicalDimensionUnit { get; init; }
    public required decimal DimensionIncrement { get; init; }
    public MidpointRounding Rounding { get; init; } = MidpointRounding.AwayFromZero;
}
```

`ShippingPhysicalDataResolver`, the packer, the provider request, and the fingerprint generator must share the same normalized objects. No component may independently reconvert or reround the values after the fingerprint is computed.

Raw provider-rate cache behavior is explicit:

```csharp
public sealed class ProviderRateCachePolicy
{
    public TimeSpan AbsoluteExpiration { get; init; } = TimeSpan.FromMinutes(5);
    public TimeSpan? SlidingExpiration { get; init; } = TimeSpan.FromMinutes(1);
    public bool InvalidateOnConfigurationRevisionChange { get; init; } = true;
    public bool InvalidateOnProviderImplementationVersionChange { get; init; } = true;
    public bool InvalidateOnCredentialRotation { get; init; } = false;
}

public interface IShippingRateCache
{
    Task<T> GetOrAddAsync<T>(
        string key,
        ProviderRateCachePolicy policy,
        Func<CancellationToken, Task<T>> factory,
        CancellationToken cancellationToken);

    ValueTask RemoveAsync(
        string key,
        CancellationToken cancellationToken);
}
```

`GetOrAddAsync` has single-flight semantics: for one tenant and cache key, concurrent misses share one in-flight factory invocation and receive the same successful result. Provider errors, cancellations, and invalid results are not cached. One caller abandoning the request must not cancel a shared provider call while other waiters remain. In multi-node deployments, implementations should combine the distributed cache with a short distributed group lock where available; optimistic duplicate suppression and provider rate limiting remain required when no distributed single-flight primitive exists.

The key includes provider implementation version and non-secret configuration revision, but never secret values. Secret rotation normally does not change rate semantics; providers may opt into invalidation when account identity or contract terms change. Time-sensitive prices are bounded by TTL and provider-supplied expiry.

### 11.3 Conditions and adjustments

Conditions and adjustments are core merchant policy. Each implementation has a stable type key and versioned configuration. Adjustment ordering is explicit and stable. Every adjustment produces an audit snapshot.

Phase 1 includes:

- `GeographicCondition`: allowed/excluded countries, administrative areas, and exact/prefix/range postal-code rules;
- `ScheduleCondition`: allowed days, local cutoff time, active date range, excluded dates, and configured time zone;
- subtotal, quantity, weight, shipping class, customer role, and coupon conditions as needed.

#### 11.3.1 Postal-code matching

Postal rules use a closed set of safe matching modes:

```csharp
public enum PostalCodeMatchType
{
    Exact,
    Prefix,
    Range,
    Wildcard, // At most one `*`; no arbitrary regular expression.
}

public sealed record PostalCodeRule
{
    public required PostalCodeMatchType MatchType { get; init; }
    public required string Value { get; init; }
    public string? RangeEnd { get; init; }
}
```

Matching is performed against a country-aware normalized postal code. The normalization profile defines trimming, casing, and whether formatting separators such as spaces or hyphens are removed. Rules are anchored to the entire normalized value:

- `Exact` compares the complete normalized value;
- `Prefix` matches only the beginning of the normalized value;
- `Range` requires compatible normalized lengths and uses a country-specific numeric or ordinal comparer;
- `Wildcard` allows one `*` placeholder and no other pattern syntax.

A malformed or country-incompatible rule fails validation in the method editor; it is never interpreted as a regular expression at runtime. Urban/rural classification is provider- or data-source-specific and remains an extension.

The final gross customer charge cannot be negative.

### 11.4 Currency

Providers should be asked for the checkout currency when supported. If a provider returns another currency, use the established Commerce conversion abstraction. If no reliable conversion is available, mark the rate unavailable rather than treating currency values as equal. Preserve the original provider amount in the quote and order snapshot.

### 11.5 Tax contract

```csharp
public interface IShippingTaxService
{
    Task<ShippingCharge> ResolveAsync(
        ShippingTaxContext context,
        CancellationToken cancellationToken);
}
```

Input is the merchant-adjusted pre-tax shipping amount plus destination, customer, method, and tax classification. Output always contains net, tax, gross, and tax mode.

The value written to `OrderAdditionalCosts` is `GrossAmount`; the tax integration may report the tax component but must not add it again to the total.

---

## 12. Quote lifecycle and security

### 12.1 Layered fingerprints

A quote uses three fingerprints instead of one brittle all-or-nothing hash.

#### Rate fingerprint

Contains values that materially affect the external request:

- provider key, implementation version, connection ID, and non-secret configuration revision;
- effective origin snapshot;
- destination;
- canonical packages, lines, dimensions, weights, declared values, and package types;
- currency;
- pickup point when sent to the provider;
- versioned provider-rate options.

It is used for grouping and raw-rate caching.

#### Policy fingerprint

Contains values that transform a provider rate into the merchant charge:

- method ID and `PolicyRevision`;
- selected carrier/service mapping;
- adjustments and ordering;
- currency-conversion policy/version;
- tax category, mode, and policy revision.

If only policy changed, a still-valid provider rate may be reused while merchant price/tax is recalculated.

#### Eligibility fingerprint

Contains values determining whether the customer/cart may use the method:

- stable line IDs, quantities, relevant attributes and prices;
- customer context used by conditions;
- destination and schedule/cutoff context;
- enabled/published state of method, connection, and origin;
- pickup-point requirements.

If eligibility changes, the method is reevaluated. Metadata not consumed by rate, policy, or eligibility does not invalidate the quote.

All fingerprints use versioned canonical serialization and never include secrets.

### 12.2 Server-side quote store

```csharp
public interface IShippingQuoteStore
{
    Task StoreAsync(ShippingQuote quote, CancellationToken cancellationToken);
    Task<ShippingQuote?> GetAsync(string quoteId, CancellationToken cancellationToken);
    Task RemoveAsync(string quoteId, CancellationToken cancellationToken);
}
```

The default implementation uses tenant-scoped distributed cache where available and tenant-scoped memory cache otherwise. Quote loss results in re-rating or a user-facing refresh response.

### 12.3 Opaque token

The protected token is intentionally small:

```csharp
public sealed record ShippingQuoteTokenPayload
{
    public required string QuoteId { get; init; }
    public required DateTime IssuedUtc { get; init; }
    public required DateTime ExpiresUtc { get; init; }
}
```

The token is protected with Orchard/ASP.NET Core data protection. The browser never receives an authoritative amount, provider connection, or service identity that can be trusted without loading the server-side quote.

### 12.4 Final validation

At checkout submission:

1. Unprotect the token.
2. Load the server-side quote.
3. Reject missing, expired, malformed, consumed quotes.
4. Rebuild eligibility and reevaluate the method.
5. Rebuild the rate fingerprint from the canonical provider request.
6. Rebuild the policy fingerprint from adjustments, conversion, and tax.
7. Confirm method, connection, and origin are enabled/published and not administratively unhealthy.
8. Apply validation policy.
9. Persist quote snapshot, display info, origin, pickup point, and line physical/origin snapshots.
10. Add exactly one shipping additional cost.
11. Append `Consumed` or `Revalidated` audit entries.
12. Remove or mark consumed.

```csharp
public enum ShippingQuoteValidationMode
{
    RequoteOnSubmit,
    HonorUntilExpiry,
}
```

`RequoteOnSubmit` is default. It may reuse a valid cached provider rate when only policy needs recalculation. Customer price changes return refreshed rates rather than silently charging a different total.

`HonorUntilExpiry` is used only when provider/method validity is guaranteed and all fingerprints match.

### 12.5 Cache and tenant behavior

Tenant-scoped caches do not need an additional tenant prefix in module keys. A tenant discriminator is required when using a host-level/shared custom store or when constructing an external idempotency/reference value whose uniqueness scope crosses tenants.

---

## 13. Core application services

Do not expose one god interface.

```csharp
public interface IShippingRateService
{
    Task<ShippingRatesResult> GetRatesAsync(
        ShippingCheckoutContext context,
        CancellationToken cancellationToken);
}

public interface IShippingSelectionService
{
    Task<ValidatedShippingSelection> ValidateAsync(
        ShippingSelection selection,
        ShippingCheckoutContext context,
        CancellationToken cancellationToken);
}

public interface IShipmentService
{
    Task<ContentItem> CreateDraftAsync(
        CreateDraftShipmentCommand command,
        CancellationToken cancellationToken);

    Task<ContentItem> PurchaseAsync(
        string shipmentContentItemId,
        CancellationToken cancellationToken);

    Task<ContentItem> CancelAsync(
        string shipmentContentItemId,
        CancellationToken cancellationToken);
}

public interface IShipmentTrackingService
{
    Task<ContentItem> RefreshAsync(
        string shipmentContentItemId,
        CancellationToken cancellationToken);
}

public interface IShippingRateLimitService
{
    ValueTask<ShippingRateLimitDecision> CheckAsync(
        ShippingRateLimitContext context,
        CancellationToken cancellationToken);
}

public interface IShippingOriginResolver
{
    Task<ShippingOriginResolutionResult> ResolveAsync(
        ShippingMethodPart method,
        IReadOnlyList<ShippableLine> lines,
        CancellationToken cancellationToken);
}

public interface IShipmentStateTransitionValidator
{
    ShipmentStateTransitionResult Validate(
        ShipmentStateSnapshot current,
        ShipmentStateSnapshot proposed,
        ShipmentStateTransitionReason reason);
}
```

Controllers, workflows, and jobs depend on the narrow service they use.

```csharp
public sealed record ShippingRatesResult
{
    public IReadOnlyList<StorefrontShippingRate> Rates { get; init; } = [];
    public IReadOnlyList<ShippingNotice> Notices { get; init; } = [];
    public bool HasShippableItems { get; init; }
}
```

Technical provider errors are logged with structured context and translated to customer-safe notices. Throttling does not reveal credential/account state.

## 14. Checkout integration

### 14.1 Checkout sequence

```text
Cart
  → customer/contact
  → billing/shipping address
  → shipping quote and optional pickup point
  → payment
  → final server validation
  → pending order with immutable shipping snapshots
  → payment completion
  → ordered state
```

The step is omitted when no line is shippable.

### 14.2 Storefront model

```csharp
public sealed record StorefrontShippingRate
{
    public required string CustomerLabel { get; init; }
    public string? CarrierLabel { get; init; }
    public string? ProviderServiceLabel { get; init; }
    public required Amount Amount { get; init; }
    public DeliveryEstimate? DeliveryEstimate { get; init; }
    public bool RequiresPickupPoint { get; init; }
    public required string QuoteToken { get; init; }
    public ShippingExtensionData DisplayData { get; init; } = ShippingExtensionData.Empty;
}
```

The browser posts only the selected quote token and pickup-point choice where required.

### 14.3 Shape conventions

```text
CheckoutShipping
CheckoutShippingRate
CheckoutShippingRate__{ProviderKey}
CheckoutPickupPoint__{ProviderKey}
OrderShippingSummary
ShipmentSummary
ShipmentAdmin
```

Provider-specific shapes are additive. The core renders a usable generic list.

### 14.4 Dynamic recalculation

Recalculate when destination, cart lines, quantities, currency, physical data, customer context used by policy, delivery mode, or price-affecting pickup point changes. Client-side debounce is optional; the server remains authoritative.

### 14.5 Endpoints

```http
POST /api/commerce/shipping/rates
POST /api/commerce/shipping/pickup-points/search
POST /api/commerce/shipping/selection/validate
```

Both MVC and API surfaces call the same application services.

### 14.6 Order creation

During pending-order creation:

- validate the quote token and load the server-side quote;
- write one shipping `OrderAdditionalCost` using gross charge;
- write `ShippingOrderPart.SelectedQuote`;
- snapshot origin and pickup point;
- snapshot physical/customs data for every order line;
- do not purchase an external shipment yet.

---

## 15. Shipment lifecycle

### 15.1 Independent state dimensions with transition invariants

```csharp
public enum ShipmentPurchaseStatus
{
    Draft,
    Ready,
    Purchasing,
    Purchased,
    Failed,
    Cancelled,
}

public enum ShipmentFulfillmentStatus
{
    Unshipped,
    Shipped,
    Delivered,
    Returned,
}

public enum ShipmentTrackingStatus
{
    Unknown,
    PreTransit,
    InTransit,
    OutForDelivery,
    AvailableForPickup,
    Delivered,
    DeliveryAttempted,
    Exception,
    Returned,
    Cancelled,
}
```

The dimensions describe different concerns, but are not unconstrained. Every command, workflow, webhook, and polling update passes through `IShipmentStateTransitionValidator`.

Minimum invariants:

- `Draft`, `Ready`, or `Purchasing` requires `Unshipped`;
- `Cancelled` purchase cannot become `Shipped` or `Delivered`;
- `Delivered` fulfillment requires purchased or externally confirmed shipment;
- tracking `Delivered` may propose fulfillment `Delivered` only through configured policy;
- fulfillment `Delivered` cannot revert to `Unshipped` without explicit administrative correction;
- tracking `Returned` may coexist with fulfillment `Shipped` until return receipt is confirmed;
- webhooks cannot bypass concurrency or validation.

Implementation may use an internal transition table or a library, but the public contract is library-neutral. Labels remain artifacts, and operation failures remain `ShipmentOperation` results.

### 15.2 Draft creation

An administrator or workflow selects:

- unfulfilled order-line quantities;
- provider connection, carrier, and service, defaulting from the order snapshot;
- origin;
- packages;
- optional provider-specific shipment options.

Packing and shipment creation use immutable order-line physical/customs snapshots, not current product content.

### 15.3 Purchase

1. Acquire concurrency protection.
2. Confirm purchase status is `Ready` or a retryable `Failed` state.
3. Create and persist a `ShipmentOperation` with operation ID, generation, idempotency key, and request fingerprint.
4. Set purchase status to `Purchasing` and persist.
5. Build an immutable provider request from shipment snapshots.
6. Call `IShipmentProvider.CreateShipmentAsync`.
7. Persist provider shipment ID, carrier/service identity, tracking references, labels, warnings, and operation result.
8. Set purchase status to `Purchased`.
9. Raise events after persistence.

If the external result is uncertain, mark the operation as requiring reconciliation. Reuse the same logical operation/idempotency key until the outcome is known. Use `IShipmentReconciliationProvider` where available.

### 15.4 Cancellation

Cancellation is an operation and audit record, not deletion. Provider rejection leaves the purchase/fulfillment state unchanged and stores a safe result. A confirmed cancellation sets purchase status to `Cancelled` only when business rules allow it and releases allocatable line quantities.

### 15.5 Fulfillment and tracking

Marking a shipment shipped changes `FulfillmentStatus`. Provider tracking updates change `TrackingStatus` and a bounded latest summary. A delivered tracking event may advance fulfillment to `Delivered` according to configured policy, but tracking data does not blindly overwrite internal state.

Full tracking event history and webhook deduplication records are stored separately to avoid unbounded content-item growth.

---

## 16. Pickup-point flow

Pickup points are modeled as a shipping-service requirement, not as an address replacement.

Sequence:

1. Customer enters a destination or search location.
2. Core determines methods requiring a pickup point.
3. Core resolves the method's provider connection and calls the provider's pickup-point capability with that connection context.
4. Customer selects one provider point ID.
5. Core revalidates the point against the same provider connection, carrier, and service.
6. The point ID contributes to the rate fingerprint when sent to the provider and to the eligibility fingerprint whenever selection is required.
7. Name, address, coordinates, and opening-hour summary are snapshotted into the order.
8. Shipment creation receives the normalized snapshot and provider point ID.

Provider modules may render a map widget, but they must support a usable list-based fallback where possible.

A selected point must never be accepted only because it was posted by the browser. It must be fetched or validated against the provider.

---

## 17. Administration UX

Recommended menu:

```text
Commerce
└─ Shipping
   ├─ Provider Connections
   ├─ Origins
   ├─ Methods
   ├─ Shipments
   ├─ Package Types
   └─ Settings
```

### 17.1 Provider connections

List and editor show:

- display text;
- provider;
- enabled/published state;
- environment;
- account reference safe for display;
- configuration revision;
- credential version, last rotation time, and rotation-required warning;
- cached connection-health state and test-connection action;
- declared/effective capability summary;
- shipping methods referencing the connection.

Secrets are edited through a protected secret mechanism and are never redisplayed in full.

### 17.2 Origins

List and editor show dispatch address, contact details, package types, enabled state, and revision. Phase 1 methods reference exactly one origin.

### 17.3 Shipping methods

List columns:

- display text;
- published/enabled state;
- provider connection;
- carrier/service;
- origin;
- packer;
- condition and adjustment summaries;
- sort order;
- policy revision.

Provider connection selection filters discoverable services. Draft edits do not affect checkout until published. Referenced methods are disabled/archived rather than hard-deleted by default.

### 17.4 Order shipping panel

Display selected method text, provider connection snapshot, carrier/service, gross/net/tax charge, provider amount, delivery estimate, origin, pickup point, line physical snapshots, fulfillment progress, and related shipments queried through the shipment index.

### 17.5 Shipment editor

Display/actions:

- internal shipment ID and optional order number snapshot;
- provider connection, carrier, and service;
- origin/destination snapshots;
- allocated lines and packages;
- independent purchase, fulfillment, and tracking statuses;
- operation history and reconciliation state;
- labels and tracking summary;
- purchase, retry/reconcile, cancel, mark shipped, and refresh tracking actions.

---

## 18. Settings and secrets

### 18.1 Core settings

```csharp
public sealed class ShippingOptions
{
    public string DefaultPackerKey { get; set; } = ShippingPackerKeys.SinglePackage;
    public string? DefaultOriginContentItemId { get; set; }
    public int DefaultQuoteLifetimeSeconds { get; set; } = 300;
    public int ProviderTimeoutSeconds { get; set; } = 10;
    public bool RequireWeight { get; set; } = true;
    public bool RequireDimensions { get; set; }
    public bool AutomaticallyCreateDraftShipment { get; set; }
    public bool AutomaticallyPurchaseShipment { get; set; }
    public bool EnableTrackingPolling { get; set; }

    public int MaxRateRequestsPerCartPerMinute { get; set; } = 10;
    public int MaxRateRequestsPerSessionPerHour { get; set; } = 50;
    public int MaxRateRequestsPerTenantPerMinute { get; set; } = 200;
    public int MinimumMillisecondsBetweenEquivalentRequests { get; set; } = 750;

    public int SuccessfulOperationRetentionDays { get; set; } = 90;
    public int FailedOperationRetentionDays { get; set; } = 365;
    public int TrackingEventRetentionDaysAfterDelivery { get; set; } = 30;
}
```

Rate limiting is server-side at cart/session/tenant scopes using privacy-preserving keys. Client debounce is UX only.

### 18.2 Provider connection secret store

```csharp
public interface IShippingProviderConnectionSecretStore
{
    Task<ShippingSecretVersion?> GetActiveAsync(
        string credentialReference,
        CancellationToken cancellationToken);

    Task<SecretRotationResult> RotateAsync(
        string credentialReference,
        IReadOnlyDictionary<string, string> newSecrets,
        int expectedCurrentVersion,
        CancellationToken cancellationToken);

    Task DisableAsync(
        string credentialReference,
        CancellationToken cancellationToken);
}

public sealed record ShippingSecretVersion
{
    public required int Version { get; init; }
    public required IReadOnlyDictionary<string, string> Values { get; init; }
    public required DateTime ActivatedUtc { get; init; }
}
```

The default contract returns only the active secret set. Historical credential retrieval is not required and should be avoided unless an external secret manager provides an audited policy. Operation records retain only the credential version used.

Secrets are tenant-specific, atomically rotated, redacted, and excluded from business data and cache keys. A missing/disabled reference makes the connection unhealthy. Destructive removal of a referenced secret requires explicit confirmation.

## 19. Events and workflows

### 19.1 .NET events

```csharp
public interface IShippingEvents
{
    Task RatesCalculatedAsync(ShippingRatesCalculatedContext context);
    Task QuoteSelectedAsync(ShippingQuoteSelectedContext context);
    Task ShipmentCreatedAsync(ShipmentEventContext context);
    Task ShipmentPurchaseCompletedAsync(ShipmentOperationEventContext context);
    Task ShipmentPurchaseFailedAsync(ShipmentOperationEventContext context);
    Task ShipmentReconciliationRequiredAsync(ShipmentOperationEventContext context);
    Task ShipmentCancelledAsync(ShipmentEventContext context);
    Task LabelsCreatedAsync(ShipmentLabelsEventContext context);
    Task TrackingUpdatedAsync(TrackingUpdatedContext context);
    Task ShipmentDeliveredAsync(ShipmentEventContext context);
}
```

Events are raised after the relevant state and operation records are persisted.

### 19.2 Workflow triggers

```text
Shipping Quote Selected
Shipment Created
Shipment Purchase Completed
Shipment Purchase Failed
Shipment Reconciliation Required
Shipment Cancelled
Shipment Marked Shipped
Shipment Tracking Updated
Shipment Delivered
Shipment Tracking Exception
```

### 19.3 Workflow activities

```text
Create Draft Shipment
Purchase Shipment
Reconcile Shipment Purchase
Cancel Shipment
Mark Shipment Shipped
Refresh Tracking
Send Shipment Notification
```

Purchase activities reuse the persisted operation generation/idempotency key. Workflow retry must not create a new logical external purchase attempt unless an administrator explicitly starts a new generation.

---

## 20. Indexes and queries

### 20.1 Provider connection and origin indexes

```csharp
public sealed class ShippingProviderConnectionIndex : MapIndex
{
    public string ContentItemId { get; set; } = string.Empty;
    public bool Enabled { get; set; }
    public string ProviderKey { get; set; } = string.Empty;
    public string Environment { get; set; } = string.Empty;
    public int CredentialVersion { get; set; }
    public bool CredentialRotationRequired { get; set; }
    public int ConfigurationRevision { get; set; }
}

public sealed class ShippingOriginIndex : MapIndex
{
    public string ContentItemId { get; set; } = string.Empty;
    public bool Enabled { get; set; }
    public string CountryCode { get; set; } = string.Empty;
    public int ConfigurationRevision { get; set; }
}
```

### 20.2 Shipping method index

```csharp
public sealed class ShippingMethodIndex : MapIndex
{
    public string ContentItemId { get; set; } = string.Empty;
    public bool Enabled { get; set; }
    public string ProviderConnectionContentItemId { get; set; } = string.Empty;
    public string OriginContentItemId { get; set; } = string.Empty;
    public string? CarrierCode { get; set; }
    public string ServiceCode { get; set; } = string.Empty;
    public int SortOrder { get; set; }
    public int PolicyRevision { get; set; }
}
```

Checkout queries published methods only.

### 20.3 Shipment and operation indexes

```csharp
public sealed class ShipmentIndex : MapIndex
{
    public string ContentItemId { get; set; } = string.Empty;
    public string ShipmentId { get; set; } = string.Empty;
    public string OrderContentItemId { get; set; } = string.Empty;
    public string? OrderNumberSnapshot { get; set; }
    public string ProviderKey { get; set; } = string.Empty;
    public string ProviderConnectionContentItemId { get; set; } = string.Empty;
    public string? CarrierCode { get; set; }
    public string ServiceCode { get; set; } = string.Empty;
    public ShipmentPurchaseStatus PurchaseStatus { get; set; }
    public ShipmentFulfillmentStatus FulfillmentStatus { get; set; }
    public ShipmentTrackingStatus TrackingStatus { get; set; }
    public string? ProviderShipmentId { get; set; }
    public DateTime CreatedUtc { get; set; }
}
```

Use separate indexes/documents for tracking references, operation attempts, and webhook deduplication rather than concatenating values into one field.

### 20.4 Stores

```csharp
public interface IShippingMethodStore
{
    Task<IReadOnlyList<ContentItem>> GetPublishedEnabledAsync(
        CancellationToken cancellationToken);
}

public interface IShipmentStore
{
    Task<ContentItem?> FindByShipmentIdAsync(string shipmentId);
    Task<IReadOnlyList<ContentItem>> FindByOrderAsync(string orderContentItemId);
    Task<ContentItem?> FindByProviderShipmentIdAsync(
        string providerConnectionContentItemId,
        string providerShipmentId);
}

public interface IShipmentOperationStore
{
    Task<ShipmentOperation?> FindCurrentAsync(
        string shipmentId,
        ShipmentOperationType type,
        CancellationToken cancellationToken);

    Task<IReadOnlyList<ShipmentOperation>> GetRecentAsync(
        string shipmentId,
        int limit,
        CancellationToken cancellationToken);

    Task ArchiveAsync(DateTime olderThanUtc, CancellationToken cancellationToken);
    Task PurgeArchivedAsync(DateTime olderThanUtc, CancellationToken cancellationToken);
}
```

---

## 21. Error model

```csharp
public sealed record ShippingError
{
    public required string Code { get; init; }
    public required string Message { get; init; }
    public ShippingErrorCategory Category { get; init; }
    public bool IsRetryable { get; init; }
    public string? ProviderKey { get; init; }
    public string? ProviderConnectionContentItemId { get; init; }
    public string? ProviderCode { get; init; }
    public ShippingExtensionData ExtensionData { get; init; } = ShippingExtensionData.Empty;
}
```

Categories:

```text
Validation
Authentication
Configuration
Unavailable
RateLimit
Timeout
ProviderRejected
Conflict
NotSupported
UncertainExternalResult
Internal
```

Customer responses use localized safe messages. Admin diagnostics may include provider codes, connection IDs, operation IDs, and correlation IDs, but not credentials or unnecessary personal data.

---

## 22. Idempotency, reconciliation, and concurrency

### 22.1 Logical operation identity

Persist the operation before the external call. A purchase operation has:

```text
ShipmentId + OperationType + Generation
```

The provider idempotency value is derived from a stable tenant discriminator, provider connection, operation ID, and generation, then hashed when sent externally.

Tenant identifiers do not need to be added to tenant-scoped local cache/storage keys. They are required when the external provider account or other namespace is shared across tenants.

### 22.2 Retry semantics

- Retries of the same uncertain logical operation reuse the same generation and idempotency key.
- A new generation is created only after the previous outcome is known or an administrator explicitly resets the operation.
- Providers with native idempotency send the key through the supported header/reference field.
- Providers without native idempotency require merchant-reference lookup/reconciliation before automatic retry is enabled.
- The core promises at-least-once execution with deduplication/reconciliation, not universal exactly-once behavior.

### 22.3 Concurrency

Use optimistic concurrency on the shipment aggregate and operation store. In multi-node deployments, add a distributed lock where available around purchase/cancel/reconcile transitions. Optimistic concurrency remains the final safeguard.

Prevent:

- concurrent purchase attempts;
- purchase and cancellation races;
- a webhook overwriting a newer tracking summary;
- duplicate workflow operation generations;
- line allocation beyond remaining order quantity.

---

## 23. Webhooks

Do not expose the provider connection content-item ID as the public webhook address. Create a separately rotatable endpoint identifier:

```http
POST /api/commerce/shipping/webhooks/{providerKey}/{webhookEndpointId}
```

```csharp
public sealed record ShippingWebhookEndpoint
{
    public required string WebhookEndpointId { get; init; }
    public required string ProviderConnectionContentItemId { get; init; }
    public required string SecretReference { get; init; }
    public required DateTime CreatedUtc { get; init; }
    public DateTime? DisabledUtc { get; init; }
}
```

Processing:

1. Resolve endpoint to provider connection.
2. Preserve raw bytes.
3. Verify signature/HMAC, timestamp, and replay window using endpoint secret.
4. Translate normalized events.
5. Deduplicate by provider event ID or digest.
6. Locate shipment.
7. Validate proposed state transition.
8. Apply with optimistic concurrency.
9. Store bounded/separate history.
10. Persist before events/workflows.
11. Return promptly.

Endpoint URLs and secrets can rotate independently from the provider connection. Raw bodies are not logged by default.

## 24. Authorization and permissions

Recommended permissions:

```text
ManageShippingSettings
ManageShippingProviderConnections
ManageShippingProviderCredentials
ManageShippingOrigins
ManageShippingMethods
ViewShipments
ManageShipments
PurchaseShipments
CancelShipments
DownloadShippingLabels
ViewShippingTracking
```

A user with order-view permission should not automatically gain label-download or shipment-purchase permission.

API authorization should distinguish storefront rate requests from administrative fulfillment actions.

---

## 25. Observability

### 25.1 Structured logging

Log fields:

- tenant or tenant hash when crossing shared namespaces;
- provider key;
- provider connection content-item ID;
- carrier code;
- shipping method content-item ID;
- service code;
- cart/order/shipment/operation correlation IDs;
- operation and generation;
- duration, retry count, cache hit/miss, and result category;
- provider response code and reconciliation state.

Do not log credentials, protected quote tokens, full addresses by default, raw labels, full webhook payloads, or unredacted external request/response bodies.

### 25.2 Tracing and metrics

```text
shipping.rate.compile
shipping.provider.rate
shipping.quote.store
shipping.selection.validate
shipping.shipment.purchase
shipping.shipment.reconcile
shipping.shipment.cancel
shipping.tracking.refresh
shipping.webhook.process
```

Metrics include grouped-request count, provider calls avoided by grouping/cache, cache single-flight waits, quote-store misses, rate-limit rejections, connection-health states, rate duration/error rate, purchase and reconciliation outcomes, tracking refresh results, webhook duplicate/rejection counts, and archive/purge counts.

---

## 26. Headless API

The optional API feature should expose normalized resources without leaking Orchard internals.

### 26.1 Storefront

```http
POST /api/commerce/shipping/rates
POST /api/commerce/shipping/pickup-points/search
```

### 26.2 Administration

```http
GET  /api/commerce/orders/{orderContentItemId}/shipments
POST /api/commerce/orders/{orderContentItemId}/shipments
GET  /api/commerce/shipments/{shipmentId}
POST /api/commerce/shipments/{shipmentId}/purchase
POST /api/commerce/shipments/{shipmentId}/cancel
POST /api/commerce/shipments/{shipmentId}/tracking/refresh
GET  /api/commerce/shipments/{shipmentId}/labels/{labelId}
```

Use DTOs and application services. Do not serialize `ContentItem` directly as the public contract.

Storefront endpoints require anti-abuse controls, validation, and tenant-aware rate limiting because live carrier calls can be expensive.

---

## 27. Provider-specific UI and extension data

Provider-specific options can include Saturday delivery, signature, age check, locker size, cash on delivery, notifications, and pickup-point maps.

Use versioned typed envelopes rather than unrestricted mutable `JsonObject` bags:

```csharp
public sealed record ShippingExtensionData
{
    public static ShippingExtensionData Empty => new();
    public string Type { get; init; } = string.Empty;
    public int Version { get; init; } = 1;
    public JsonObject Data { get; init; } = new();
}
```

Rules:

- public boundaries receive cloned/read-only data;
- type keys are namespaced and stable;
- size limits are enforced;
- data needed after order placement is explicitly snapshotted;
- providers cannot mutate extension objects owned by core services;
- server-side validators own provider option validation.

Provider-specific displays remain additive; a generic admin and checkout experience must still work.

---

## 28. Built-in providers and non-external rate sources

The term “shipping provider” includes a built-in rate source for implementation consistency, even when no external carrier API exists.

### 28.1 Fixed-rate provider

- rates only;
- connection may contain currency/default amount settings;
- method conditions and adjustments remain core policy;
- optional delivery estimate.

### 28.2 Free shipping

Prefer a method condition plus adjustment, not carrier-specific code. A recipe/template may create the configuration.

### 28.3 Local pickup

Local pickup can be implemented as a built-in provider/rate source with no external carrier, shipment purchase, label, or tracking capability. The connection references a store/origin location and optional collection rules.

The domain documentation must not imply that every shipping provider represents a physical carrier.

---

## 29. Example provider skeleton

```csharp
public sealed class DhlShippingProvider :
    IShippingRateProvider,
    IShippingServiceCatalogProvider,
    IShipmentProvider,
    IShipmentReconciliationProvider,
    IShippingLabelProvider,
    IShippingTrackingProvider,
    IPickupPointProvider,
    IShippingWebhookHandler
{
    public string ProviderKey => "Dhl";

    public ShippingProviderDescriptor Descriptor { get; } = new()
    {
        DisplayName = new LocalizedString("DHL", "DHL"),
        Capabilities =
            ShippingProviderCapabilities.Rates |
            ShippingProviderCapabilities.ServiceDiscovery |
            ShippingProviderCapabilities.ShipmentCreation |
            ShippingProviderCapabilities.ShipmentCancellation |
            ShippingProviderCapabilities.Labels |
            ShippingProviderCapabilities.Tracking |
            ShippingProviderCapabilities.PickupPoints |
            ShippingProviderCapabilities.Webhooks |
            ShippingProviderCapabilities.Reconciliation,
        SupportsSandbox = true,
        ImplementationVersion = "1",
    };

    public Task<IReadOnlyList<ProviderShippingRate>> GetRatesAsync(
        ShippingProviderConnectionContext connection,
        ProviderRateRequest request,
        CancellationToken cancellationToken)
    {
        // Resolve credentials from connection context.
        // Map normalized origin, destination, packages, carrier/service filters,
        // and versioned provider options to the DHL API.
        // Return ProviderKey, connection ID, carrier/service codes, amount,
        // delivery estimate, and safe extension data.
        throw new NotImplementedException();
    }

    // Remaining capability methods omitted.
}
```

Registration:

```csharp
public override void ConfigureServices(IServiceCollection services)
{
    services.AddHttpClient<DhlApiClient>();
    services.AddShippingProvider<DhlShippingProvider>();
    services.AddShippingProviderConnectionEditor<DhlConnectionDisplayDriver>();
}
```

Credentials are configured per `ShippingProviderConnection` through the protected connection secret store, not through one global `DhlShippingOptions` object that limits a tenant to a single account.

---

## 29.1 Provider configuration versioning and developer template

Provider-specific `ShippingExtensionConfiguration.Version` values are migrated explicitly:

```csharp
public interface IShippingProviderConfigurationMigrator
{
    Task<ShippingConfigurationMigrationResult> MigrateAsync(
        string providerKey,
        ShippingExtensionConfiguration configuration,
        int targetVersion,
        CancellationToken cancellationToken);
}
```

A provider upgrade must not reinterpret old configuration silently. It supplies deterministic migrations or reports that administrative action is required.

The project should provide a template or sample package:

```bash
dotnet new orchardcore-commerce-shipping-provider --name Dhl --key Dhl
```

The template generates provider/descriptor code, optional capability stubs, connection editor/validation, configuration migrator, fake/sandbox harness, contract tests, documentation, and a sample recipe.

## 30. Suggested internal folder layout

```text
OrchardCore.Commerce.Shipping.Abstractions/
├─ Providers/
│  ├─ IShippingProvider.cs
│  ├─ IShippingRateProvider.cs
│  ├─ IShippingServiceCatalogProvider.cs
│  ├─ IShipmentProvider.cs
│  ├─ IShipmentReconciliationProvider.cs
│  ├─ IShippingLabelProvider.cs
│  ├─ IShippingTrackingProvider.cs
│  ├─ IPickupPointProvider.cs
│  └─ IShippingWebhookHandler.cs
├─ Connections/
│  ├─ ShippingProviderConnectionContext.cs
│  ├─ IShippingProviderConnectionSecretStore.cs
│  ├─ IShippingProviderConnectionHealthService.cs
│  └─ IShippingProviderConfigurationMigrator.cs
├─ Http/
│  └─ IShippingProviderHttpClientFactory.cs
├─ Rates/
│  ├─ ProviderRateRequest.cs
│  ├─ ProviderShippingRate.cs
│  ├─ ShippingQuote.cs
│  ├─ ShippingCharge.cs
│  └─ IShippingRateCache.cs
├─ Packing/
├─ Shipments/
├─ Tracking/
└─ ValueObjects/

OrchardCore.Commerce.Shipping/
├─ Models/
│  ├─ ShippingProductPart.cs
│  ├─ ShippingProviderConnectionPart.cs
│  ├─ ShippingOriginPart.cs
│  ├─ ShippingMethodPart.cs
│  ├─ ShippingOrderPart.cs
│  └─ ShipmentPart.cs
├─ Services/
│  ├─ ShippingRateService.cs
│  ├─ ShippingSelectionService.cs
│  ├─ ShipmentService.cs
│  ├─ ShipmentTrackingService.cs
│  ├─ ShippingProviderRegistry.cs
│  ├─ ShippingProviderConnectionStore.cs
│  ├─ ShippingProviderConnectionHealthService.cs
│  ├─ ShippingProviderHttpClientFactory.cs
│  ├─ ShippingRateCache.cs
│  ├─ ShippingRateLimitService.cs
│  ├─ ShippingOriginResolver.cs
│  ├─ ShipmentStateTransitionValidator.cs
│  ├─ ShippingPhysicalDataResolver.cs
│  ├─ ShippingRequestGroupingService.cs
│  ├─ ShippingTaxService.cs
│  └─ ShippingQuoteProtector.cs
├─ Stores/
│  ├─ ShippingQuoteStore.cs
│  ├─ ShipmentStore.cs
│  ├─ ShipmentOperationStore.cs
│  ├─ TrackingEventStore.cs
│  ├─ ShippingWebhookDeduplicationStore.cs
│  ├─ ShippingWebhookEndpointStore.cs
│  ├─ ShippingProviderConnectionHealthStore.cs
│  └─ ShippingDocumentStore.cs
├─ Packers/
├─ Conditions/
├─ Adjustments/
├─ Indexes/
├─ Drivers/
├─ Controllers/
└─ Workflows/
```

---

## 31. Data migration plan

### 31.1 Legacy order-line ID upgrade mini-spec

The stable-line-ID upgrade is a compatibility subsystem with implementation, UX, and release criteria.

It provides:

- tenant-scoped dry run with readable, auto-resolvable, ambiguous, colliding, and corrupt counts;
- bounded restartable batches and checkpoints;
- idempotence;
- optimistic concurrency;
- canonical fixtures for every historical `OrderLineItem` shape;
- immutable algorithm version;
- optional in-place persistence and sidecar mappings;
- per-order audit data including structure fingerprint and original positions;
- collision checks before shipment allocation;
- repair UI showing original lines, identity inputs, proposed IDs, and allocations;
- backup/restore guidance.

Resolution precedence is defined in Section 7.2.1 and uses historical order data only.

```csharp
public interface ILegacyOrderLineIdentityResolver
{
    OrderLineIdentity Resolve(
        string orderContentItemId,
        IReadOnlyList<OrderLineItem> lines,
        int lineIndex);
}
```

Release fixtures cover duplicate lines, reordered attributes, missing SKUs, deleted products, currency formats, malformed optional data, historical models, line reordering, and existing allocations.

Only ambiguous/corrupt orders are blocked, and the notice links to repair.

### Migration 1 — shared primitives

- add validated unit/value objects;
- add `OrderAdditionalCostKinds.Shipping`;
- require stable line IDs for newly created order lines;
- implement the legacy line-ID resolver, dry-run report, deterministic synthetic fallback, and controlled persist-upgrader described in Section 31.1.

### Migration 2 — checkout configuration

- define `ShippingProductPart`;
- define `ShippingProviderConnection` content type and index;
- define `ShippingOrigin` content type and index;
- define versionable/localizable `ShippingMethod` content type and index;
- register protected provider-connection secret storage;
- register fixed-rate and local-pickup built-in providers.

### Migration 3 — order integration

- define and attach `ShippingOrderPart`;
- add selected quote, origin, pickup-point, and order-line physical/customs snapshots;
- update order creation to preserve line IDs and resolved physical data;
- complete shipping tax/additional-cost adapter integration.

### Migration 4 — fulfillment

- define `Shipment` content type and indexes;
- add shipment operation, tracking-event, and webhook-deduplication stores;
- add label document storage;
- add fulfillment permissions/admin UI.

### Migration 5 — workflows and API

- register activities/triggers;
- add optional API routes;
- add webhook endpoints and scheduled tracking polling.

Migrations are idempotent and safe for tenants with customized `Order` content types. A missing persisted line ID alone does not block shipping: deterministic synthetic IDs provide a compatibility path. Only orders whose lines are ambiguous, colliding, unreadable, or corrupt remain blocked until repaired or explicitly upgraded.

---

## 32. Testing strategy

### 32.1 Unit tests

- value-object validation and normalization;
- stable legacy line-ID upgrade behavior;
- provider-request and checkout fingerprints;
- canonical unit conversion, precision, rounding, property ordering, and normalization-profile versioning;
- grouping of near-equivalent values such as `1.00001 kg` and `1.0 kg` under the configured profile;
- request grouping equivalence;
- quote-store loss, expiry, and token tampering;
- method publishing/revision invalidation;
- physical-data/order snapshot consistency;
- tax charge net/tax/gross invariants;
- tax-module compatibility proving that resolved shipping tax is not added twice;
- legacy line-ID deterministic fallback, duplicate-line occurrence handling, collisions, dry runs, and idempotent persist-upgrades;
- standard-box selection, no-fit failure, weight/dimension limits, and dimensional-weight regression scenarios;
- conditions, adjustments, and packing;
- independent shipment state transitions;
- operation-generation and reconciliation behavior;
- webhook deduplication and tracking-summary concurrency.

### 32.2 Provider contract and integration tests

Every provider module supplies:

- fast contract tests using fake HTTP or sanitized fixtures;
- connection isolation tests;
- descriptor/interface capability consistency;
- cancellation and timeout behavior;
- provider/connection/carrier/service identity validation;
- amount/currency/expiry validation;
- safe no-service, authentication, rate-limit, and provider-error mapping;
- idempotency/reference mapping;
- reconciliation tests where implemented;
- configuration migration tests;
- no provider-side persistence effects.

Sandbox tests are opt-in and credential-gated, never use production credentials, and may be excluded from normal CI when availability is not guaranteed.

A shared `ShippingProviderContractTestKit` lets third-party providers run the same assertions.

### 32.3 Checkout integration tests

- multiple methods sharing one request cause one provider call;
- one provider connection failure does not suppress healthy rates;
- changing cart/address/currency/method/connection/origin revision invalidates a quote;
- quote-store loss triggers re-rating/refresh rather than client trust;
- selected shipping gross amount appears exactly once in totals;
- net/tax/gross breakdown is retained without double counting;
- order physical snapshots survive product edits/deletion;
- historical order display survives method/connection disablement.

### 32.4 Fulfillment integration tests

- draft shipment uses order snapshots, not current product data;
- allocated quantities cannot exceed ordered quantities;
- repeated purchase reuses the logical operation generation;
- uncertain outcomes enter reconciliation rather than blind retry;
- provider shipment lookup resolves an uncertain purchase;
- labels are external documents with authorized download;
- tracking history does not cause unbounded shipment JSON growth;
- cancellation preserves audit history and releases quantities.

---

### 32.5 Performance and load tests

Automated load tests cover:

- concurrent rate bursts for one and many carts;
- cache-hit latency, one-factory single-flight behavior, waiter cancellation, and stampede prevention;
- tenant/cart/session rate limits;
- bounded provider concurrency and cancellation;
- one external call per equivalent group;
- graceful behavior when one connection is unhealthy;
- concurrent purchase attempts proving one logical purchase;
- webhook/polling races against state validation;
- archive maintenance over representative yearly volumes.

Microbenchmarks may cover canonical serialization, fingerprinting, and packing, but do not replace endpoint-level load tests.

## 33. Performance requirements

Suggested targets under normal provider conditions:

- core rate orchestration excluding external calls: under 100 ms;
- cached rate response: under 250 ms end-to-end;
- provider timeout default: 10 seconds, configurable per provider connection within provider-defined safe bounds;
- provider HTTP resilience uses bounded timeout, telemetry, circuit breaking, and retry policies that never blindly retry non-idempotent writes;
- parallelize independent provider calls with bounded concurrency;
- cancel outstanding calls when the request is abandoned;
- avoid loading full historical shipment content when only an order summary is needed;
- use indexes for all admin list and webhook lookup queries;
- prevent cache stampedes with single-flight/group locks;
- enforce tenant/cart/session rate limits before provider calls;
- execute retention/archive jobs in bounded batches.

A method/provider timeout should produce a partial result when other rates are available.

---

## 34. Security and privacy checklist

- [ ] Quote tokens are protected, opaque, and short-lived.
- [ ] Full authoritative quotes remain server-side.
- [ ] Server reconstructs and verifies checkout fingerprints.
- [ ] Browser-posted prices and service/provider identities are never trusted.
- [ ] Provider connection credentials remain in a protected tenant secret store.
- [ ] Provider secrets are never copied into content items, quotes, orders, shipments, events, or logs.
- [ ] Pickup points are revalidated.
- [ ] Label download requires explicit authorization.
- [ ] Webhooks resolve a connection, verify signatures, and resist replay.
- [ ] Provider endpoints are configured by trusted provider code/connection settings.
- [ ] Extension data has type/version/size limits.
- [ ] Tenant-scoped caches rely on Orchard isolation; shared/external namespaces use a tenant-derived discriminator.
- [ ] Shipment operations are persisted before external calls.
- [ ] Uncertain external results use reconciliation rather than blind retry.
- [ ] Raw provider errors are not shown to customers.
- [ ] Retention rules exist for labels, webhook payloads, operations, and tracking events.

---

## 35. MVP and phased delivery

### Phase 1 — checkout/rating kernel

Deliver:

- validated physical value objects and physical-data resolver;
- stable IDs for new lines plus deterministic synthetic compatibility IDs, dry-run reporting, and controlled persistence for legacy orders;
- `ShippingProductPart`;
- shipping providers and provider connections;
- explicit origins;
- published/versioned shipping methods;
- provider registry, descriptor capability validation, and rate contract;
- one bounded standard-box packer with merchant-configured box sizes, packing factor, and hard weight/dimension limits;
- grouped provider-call orchestration, layered fingerprints, explicit cache policy, and short-lived raw-rate cache;
- geographic/schedule merchant conditions and adjustments;
- completed tax-module compatibility spike and finalized shipping tax/charge integration;
- server-side quote store and opaque tokens;
- checkout UI/API and final validation;
- immutable selected quote, origin, pickup point, and order-line physical/customs snapshots;
- fixed-rate and local-pickup built-in providers;
- connection health checks, checkout rate limiting, provider configuration migration, tests, and documentation.

Not in Phase 1:

- shipment content items;
- external shipment purchase;
- labels;
- tracking;
- webhooks;
- workflows;
- multiple origins per order;
- advanced packing.

Validate Phase 1 with at least one direct carrier and one aggregator or otherwise structurally different provider before freezing the fulfillment contracts.

### Phase 2 — manual fulfillment

Deliver:

- shipment aggregate with orthogonal states and validated transitions;
- partial line allocation using order snapshots;
- durable shipment-operation records with retention/archive policy;
- purchase, cancellation, and reconciliation capabilities;
- label storage/download;
- manual tracking refresh and bounded summary;
- order/shipment admin UI;
- permissions and concurrency controls.

### Phase 3 — automation

Deliver:

- pickup-point maps/advanced provider UI;
- independently rotatable webhook endpoints, signature secrets, and deduplication;
- scheduled tracking polling;
- workflow events and activities;
- optional headless admin API;
- additional packers and service discovery improvements.

### Phase 4 — advanced logistics

Potential additions include multiple origins/warehouses, manifests, returns, customs documents, insurance, optimization, reconciliation analytics, and carrier-account cost reporting.

---

## 36. Acceptance criteria

### Phase 1

1. A tenant can configure two connections for the same provider and reference them from different methods; credential rotation advances a version without exposing secrets.
2. A method references one published provider connection and resolves exactly one effective origin; incompatible product-origin overrides make the method unavailable.
3. Products resolve validated physical data and new order lines have stable IDs.
4. Equivalent methods share one provider call through request grouping after canonical unit conversion, deterministic rounding, ordering, and dimension-aware packing.
5. Direct-carrier and aggregator rates can preserve different provider and carrier identities.
6. One failed provider request group does not suppress healthy rates.
7. Checkout selects an opaque token backed by a server-side quote.
8. Quote loss or expiry causes re-rating/refresh, never client-value trust.
9. Rate, policy, and eligibility changes are validated independently; unrelated metadata changes do not invalidate a quote.
10. The selected gross shipping charge is included exactly once in order totals.
11. Net/tax/gross values are retained and an integration test proves that shipping tax is not double-counted by the Commerce tax pipeline.
12. The default packer verifies every item orientation/dimension, total volume, weight, and hard limits; it fails explicitly when no box fits.
13. Legacy orders with resolvable lines can be shipped through deterministic synthetic IDs; ambiguous/corrupt legacy orders are blocked with an actionable reason.
14. The order stores immutable selected-quote, layered fingerprint, bounded quote-audit, origin, pickup-point, and per-line physical/customs snapshots.
15. Historical order display works after product, method, origin, or provider connection changes.
16. A third-party rate provider can be implemented with validated descriptor/capability registration, configuration migrations, and the shared contract-test kit.
17. Checkout rate limits and connection-health failures degrade gracefully without suppressing healthy methods.
18. Provider-rate cache policy handles TTL, implementation-version changes, configuration revisions, and secret rotation explicitly.

### Phase 2

1. Shipment allocation uses resolved persisted or deterministic synthetic line IDs and immutable order shipping snapshots.
2. Shipment quantities cannot exceed remaining ordered quantities.
3. Purchase, fulfillment, and tracking statuses evolve independently but every proposed combination passes transition validation.
4. An operation record exists before every external purchase/cancel/reconcile call.
5. Retry of an uncertain purchase reuses the same logical generation/idempotency key.
6. Providers without native idempotency can use reconciliation before automatic retry.
7. Labels are stored outside content JSON and require permission to download.
8. Tracking history and webhook deduplication do not create unbounded shipment content JSON.
9. Cancellation preserves audit history and releases quantities only after a confirmed outcome.
10. Operation and tracking stores apply bounded retention/archive rules while preserving reconciliation-required and legal-hold records.
11. Webhook endpoint identifiers and secrets can rotate independently from provider connections.

---

## 37. Architectural invariants

1. **Shipping provider, provider connection, carrier, service, method, provider rate, shipping quote, and shipment remain distinct concepts.**
2. **Shipping methods reference provider connections, not only provider keys.**
3. **Provider capabilities are optional and small; descriptor declarations are validated projections of implemented interfaces.**
4. **The core owns policy, quote/order/shipment persistence, and authorization.**
5. **Providers consume normalized DTOs and resolved connection contexts, not Orchard UI or mutable content objects.**
6. **Equivalent provider requests are grouped and called once.**
7. **Physical values are converted to canonical units, deterministically rounded, ordered, and versioned before packing, grouping, caching, provider calls, or fingerprinting.**
8. **Authoritative shipping quotes are stored server-side, use layered rate/policy/eligibility fingerprints, and are selected by opaque tokens.**
9. **Client-posted prices and identities are never authoritative.**
10. **Selected quote, accepted layered fingerprints, bounded quote audit, origin, pickup point, and physical line data are snapshotted into the order.**
11. **Shipment lines use persisted order-line IDs or versioned deterministic synthetic IDs; random IDs are never generated during deserialization.**
12. **The default MVP packer validates individual dimensions, orientations, volume, weight, and hard limits against bounded boxes.**
13. **Shipping tax is resolved once and the order-total calculation boundary prevents double taxation.**
14. **Shipment purchase, fulfillment, and tracking are independent dimensions constrained by transition invariants.**
15. **External operation attempts are durable and support reconciliation.**
16. **Labels and unbounded operational history are stored outside shipment content JSON.**
17. **Operational calls use stable IDs/codes; display text is historical/UI data only.**
18. **Tenant prefixes are added only when the target namespace is not already tenant-scoped.**
19. **Secrets are versioned and rotated atomically, but secret values never enter business records or fingerprints.**
20. **Provider calls are protected by health checks, bounded concurrency, explicit single-flight caching, resilient HTTP policies, and server-side rate limits.**
21. **Operational history has explicit retention, archive, purge, and legal-hold behavior.**

---

## 38. Deferred implementation decisions

The following may be finalized during implementation without changing the revised domain model:

- whether condition/adjustment configuration is stored as part JSON, child content items, or another versioned representation behind the same typed envelope;
- the exact display-driver hierarchy for provider connection and method editor shapes;
- whether normalized provider failures use result objects, exceptions, or a combined internal pattern;
- the distributed cache and distributed lock implementations;
- whether polling uses an Orchard background task or scheduled workflow;
- whether tracking history uses YesSql documents or a dedicated table behind `ITrackingEventStore`;
- whether a convenience facade combines the narrow application services;
- whether transition validation uses an internal table or a third-party state-machine library;
- which direct carrier and aggregator are used to validate Phase 1.

The following are no longer deferred: provider connections, explicit origins, server-side quote storage, layered fingerprints, order physical snapshots, deterministic legacy compatibility, canonical normalization, grouped requests, dimension-aware standard-box packing, tenant-settings package types, the concrete tax contract, validated orthogonal states, credential rotation metadata, connection health, rate limiting, secure webhook identifiers, and operation retention.

---

## 38.1 Review decisions incorporated through version 2.3

Versions 2.2 and 2.3 incorporate the critical review and final contract refinements with these clarifications:

- capability declarations are UI/validation metadata; interfaces remain authoritative and consistency is validated;
- secret versioning/rotation is accepted, but historical secret retrieval is not a default requirement;
- layered fingerprints replace one brittle quote fingerprint;
- display labels are grouped into `ShippingDisplayInfo`;
- independent shipment states remain, with mandatory transition validation;
- legacy identity canonicalization, sidecar mappings, and repair UX are specified;
- cache policy, connection health, rate limiting, retention, secure webhooks, provider migration, and load testing are added;
- package selection is dimension/orientation-aware;
- product-origin overrides are opt-in and never silently collapse multiple origins;
- package types are tenant settings for the MVP;
- shipping tax mode, connection-health status, and postal match modes are closed enums with documented semantics;
- raw-rate caching exposes explicit single-flight behavior;
- provider HTTP clients use bounded, operation-aware resilience policies.

## 39. References and design sources

### Orchard Core and OrchardCore Commerce

- OrchardCore Commerce repository: <https://github.com/OrchardCMS/OrchardCore.Commerce>
- OrchardCore Commerce payment providers: <https://github.com/OrchardCMS/OrchardCore.Commerce/blob/main/docs/topics/payment-providers.md>
- OrchardCore Commerce workflow events: <https://github.com/OrchardCMS/OrchardCore.Commerce/blob/main/docs/topics/workflows.md>
- OrchardCore Commerce taxation documentation: <https://github.com/OrchardCMS/OrchardCore.Commerce/blob/main/docs/features/taxation.md>
- OrchardCore Commerce third-party tax-service issue: <https://github.com/OrchardCMS/OrchardCore.Commerce/issues/159>
- Orchard Core modules: <https://docs.orchardcore.net/en/main/reference/modules/Modules/>
- Orchard Core tenants: <https://docs.orchardcore.net/en/main/reference/modules/Tenants/>
- Orchard Core tenant configuration: <https://docs.orchardcore.net/en/main/reference/modules/Configuration/>

### Shipping API references

- Drupal Commerce Shipping user/developer documentation: <https://docs.drupalcommerce.org/v2/user-guide/shipping/>
- WooCommerce Shipping Method API: <https://developer.woocommerce.com/docs/features/shipping/shipping-method-api/>
- PrestaShop carrier modules: <https://devdocs.prestashop-project.org/9/modules/carrier/>
- PrestaShop 9.1 multi-shipment changes: <https://devdocs.prestashop-project.org/9/modules/core-updates/9.1/>

---

## 40. Recommended first implementation pull requests

1. Run the OrchardCore Commerce tax compatibility spike and implement/prove the typed additional-cost tax-resolver contract.
2. Add the shipping abstractions project, validated units, extension envelopes, shipping charge model, and canonical normalization profile.
3. Add stable IDs plus the explicit legacy canonicalizer, sidecar mapping, dry-run/repair UX, and controlled upgrader.
4. Add `ShippingProductPart`, canonical physical-data resolver, and fingerprint determinism tests.
5. Add `ShippingProviderConnection`, versioned/rotatable secret store, health store, index, and admin UI.
6. Add `ShippingOrigin` content type, index, snapshot model, and admin UI.
7. Add registry capability validation, connection context/health, rate contracts, provider HTTP resilience factory, configuration migrations, fake provider, and contract tests.
8. Add versionable/localizable `ShippingMethod` content type, lifecycle rules, index, and admin UI.
9. Add tenant package settings, dimension/orientation-aware packing, canonical grouping, explicit single-flight rate cache, and cache policy.
10. Add geographic/schedule conditions, adjustments, currency, and finalized tax adapter.
11. Add quote store, layered fingerprints, quote audit, token protection, rate limiting, and tests.
12. Add checkout UI/API and selection validation.
13. Add `ShippingOrderPart`, selected-quote/origin/pickup-point snapshots, and per-line physical/customs snapshots.
14. Validate the kernel with one direct carrier and one aggregator or structurally different provider.
15. Add shipment aggregate, orthogonal state model with transition validator, indexes, and partial allocation.
16. Add operation store, retention/archive maintenance, purchase/cancel/reconciliation, and concurrency.
17. Add label document storage, manual tracking refresh, and admin fulfillment UI.
18. Add workflows, rotatable webhook endpoints/secrets, deduplication, polling, load tests, and optional APIs.

Each pull request includes migrations, tests, documentation, and one focused end-to-end scenario.
