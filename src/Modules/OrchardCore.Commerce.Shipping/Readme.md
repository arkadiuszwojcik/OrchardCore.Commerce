# OrchardCore.Commerce.Shipping

**Status: Partial Implementation (Core scaffold created)**

This module provides a provider-neutral shipping subsystem for OrchardCore Commerce.

## What's Implemented

✅ **Abstractions Library** (`OrchardCore.Commerce.Shipping.Abstractions`)
- 50+ types: value objects (Weight, Dimensions), enums (ShippingProviderCapabilities), DTOs (ShippingQuote, ShippingPackage)
- Provider interfaces (`IShippingProvider`, `IShippingRateProvider`, `IShipmentProvider`, etc.)
- Service interfaces (`IShippingRateService`, `IShippingQuoteStore`, `IShippingPackerService`, etc.)

✅ **Core Module Scaffold**
- Content parts: `ShippingProviderConnectionPart`, `ShippingOriginPart`, `ShippingMethodPart`, `ShippingOrderPart`, `ShipmentPart`
- Content type migrations for provider connections, origins, methods, shipments
- Feature definitions: Core, API, Workflows

## What's Missing (TODO)

See implementation plan steps 17-50:

- [ ] **View models** and display/editor views for all parts
- [ ] **Display drivers** for admin UI (connections, origins, methods, shipments)
- [ ] **Services**: rate fetching, quote storage, packing, tax calculation, document storage
- [ ] **Permissions** and navigation providers
- [ ] **APIs**: REST endpoints for rate queries, quote retrieval
- [ ] **Workflows**: events (quote fetched, shipment purchased, tracking updated) and tasks
- [ ] **Tests**: unit tests, integration tests for services and providers
- [ ] **DHL provider module** (`OrchardCore.Commerce.Shipping.Dhl`) - skeleton implementation
- [ ] **Documentation**: MkDocs pages for configuration and provider authoring

## Design Documentation

Full specification: `docs/new_module/OrchardCore-Commerce-Shipping-Design.md`

## Next Steps

1. Implement core services (`ShippingRateService`, `ShippingQuoteStore`, `DefaultPackerService`)
2. Create display drivers and admin views for configuration
3. Add permissions and navigation
4. Implement API endpoints
5. Create workflow activities
6. Build DHL provider module as reference implementation
7. Write tests and documentation

---

**Note:** This is a foundational scaffold. The shipping subsystem design is complete but implementation requires ~250 additional files across services, drivers, views, APIs, workflows, providers, tests, and docs.
