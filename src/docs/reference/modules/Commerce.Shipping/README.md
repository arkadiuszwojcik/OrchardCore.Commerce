# Commerce Shipping (`OrchardCore.Commerce.Shipping`)

**Status: Partial Implementation**

Provider-neutral shipping subsystem for OrchardCore Commerce.

## Overview

The Shipping module provides:

- **Provider Connections**: Store credentials and settings for carrier APIs (DHL, FedEx, UPS, etc.)
- **Shipping Origins**: Define warehouse/fulfillment center addresses
- **Shipping Methods**: Create customer-facing shipping options with markup and availability rules
- **Rate Quoting**: Fetch real-time shipping rates during checkout
- **Order Integration**: Store selected shipping quote snapshots on orders
- **Shipment Management**: Track fulfillment, labels, and carrier tracking
- **Workflows**: Automate actions based on shipping events
- ** REST API**: Query rates and manage shipments programmatically

## Features

This module provides three features:

### OrchardCore.Commerce.Shipping (Core)

Core shipping functionality.

**Dependencies:**
- `OrchardCore.Contents`
- `OrchardCore.Commerce`

### OrchardCore.Commerce.Shipping.Api

RESTful API endpoints for shipping operations.

**Dependencies:**
- `OrchardCore.Commerce.Shipping`
- `OrchardCore.Apis`

### OrchardCore.Commerce.Shipping.Workflows

Workflow activities and events for shipping automation.

**Dependencies:**
- `OrchardCore.Commerce.Shipping`
- `OrchardCore.Workflows`

## Configuration

### 1. Set Up Provider Connection

1. Navigate to **Commerce > Shipping > Provider Connections** and click **Add Connection**.
2. Pick your carrier (e.g. InPost) in the **Available Providers** popup. The popup lists every provider whose module is enabled.
3. Fill in the shared fields (name, enabled, test mode) and the carrier-specific fields (e.g. API token and organization ID). The carrier-specific fields come from the provider's own module.
4. Save. A provider's editor can offer its own connection test, as the InPost editor does with its **Test connection** button.

### 2. Define Shipping Origin

1. Navigate to **Content > New > Shipping Origin**
2. Enter warehouse address
3. Mark as default if needed
4. Publish

### 3. Create Shipping Method

1. Navigate to **Content > New > Shipping Method**
2. Select provider connection and service
3. Select origin
4. Configure markup/availability rules
5. Publish

## Architecture

See the [full design documentation](../../../../docs/new_module/OrchardCore-Commerce-Shipping-Design.md) for complete specifications.

### Content Types and Documents

- **Provider connections** (`ShippingProviderConnection`): a plain entity stored together with all other connections in one document, managed under **Commerce > Shipping > Provider Connections**. It is not a content item. Fields every connection has (name, enabled, test mode) live on the entity, the settings specific to a provider are stored in its `Properties`, and secrets are kept in the secret store.
- **ShippingOrigin**: Warehouse addresses
- **ShippingMethod**: Customer-facing options
- **Shipment**: Physical shipment tracking

### Content Parts

- **ShippingOrderPart**: Attached to Order; stores selected shipping quote
- All content types above use dedicated parts

## Shipping Provider Development

See [Shipping Provider Authoring Guide](./provider-authoring.md).

### Adding a connection type for your provider

The list, the **Add Connection** popup and the shared fields of a connection belong to this module. Your provider module owns what is specific to it, the same way the URL Rewriting module lets each rule type define its own editor:

1. Register your `IShippingProvider`. Its `ProviderKey` and `GetDescriptor()` (`DisplayName`, `Description`) produce the card in the popup.
2. Add a settings class for the non-secret fields. It is stored in the connection's `Properties` under the name of the class.
3. Add a `DisplayDriver<ShippingProviderConnection>` that returns `null` unless `connection.ProviderKey` is your provider's key. In `Edit` build your editor shape, in `UpdateAsync` validate the input, store the settings with `connection.Put(settings)` (call `connection.MarkConfigurationChanged()` when they changed) and queue secrets with `connection.SetCredential(key, value)`. The secrets are written when the connection is saved, so nothing is stored if the form is invalid. Never put secrets into `Properties`.
4. Add the editor view (`<ShapeName>.cshtml`) and register the driver with `services.AddDisplayDriver<ShippingProviderConnection, YourDriver>()`.
5. In the provider, read the settings with `context.GetSettings<YourSettings>()` and the secrets from `context.Credentials`.

See `InPostConnectionDisplayDriver` in `OrchardCore.Commerce.Shipping.InPost` for a complete example.

## API Reference

See [Shipping API Reference](./api-reference.md).

## Current Implementation Status

✅ **Complete:**
- Abstractions library with 50+ types
- Content part models
- Content type migrations
- Module manifest and features

❌ **In Progress:**
- Display drivers and admin UI
- Core services (rate fetching, packing, quote storage)
- REST API endpoints
- Workflow activities
- Unit and integration tests

For detailed implementation status, see the [module README](../../../../src/Modules/OrchardCore.Commerce.Shipping/Readme.md).
