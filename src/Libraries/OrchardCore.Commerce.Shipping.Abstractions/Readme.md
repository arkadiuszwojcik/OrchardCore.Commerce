# OrchardCore.Commerce.Shipping.Abstractions

This library contains the core abstractions, interfaces, and DTOs for shipping provider integration in OrchardCore Commerce.

## What's Included

- **Shipping Provider Interface** (`IShippingProvider`) - base contract for real-time rate providers
- **Value Objects** - `ShippingQuote`, `ShippingAddress`, `Dimensions`, `Weight`, rate models, and package specifications
- **Provider Capability Flags** - `ShippingProviderCapabilities` enum for tracking purchase, label, tracking features
- **DTOs** - standardized request/response models for carrier APIs

## For Provider Authors

Implement `IShippingProvider` to integrate with any shipping carrier (DHL, FedEx, UPS, etc.). The core shipping module handles configuration, storage, workflows, and UI—you supply the carrier API integration.

See [OrchardCore.Commerce.Shipping documentation](../../docs/reference/modules/Commerce.Shipping/README.md) for full implementation guidance.
