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

1. Navigate to **Content > New > Shipping Provider Connection**
2. Select your carrier (e.g., DHL)
3. Enter API credentials
4. Test the connection
5. Publish

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

### Content Types

- **ShippingProviderConnection**: Carrier credentials and settings
- **ShippingOrigin**: Warehouse addresses
- **ShippingMethod**: Customer-facing options
- **Shipment**: Physical shipment tracking

### Content Parts

- **ShippingOrderPart**: Attached to Order; stores selected shipping quote
- All content types above use dedicated parts

## Shipping Provider Development

See [Shipping Provider Authoring Guide](./provider-authoring.md).

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
