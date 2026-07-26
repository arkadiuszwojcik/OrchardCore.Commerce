# DHL Shipping Provider (`OrchardCore.Commerce.Shipping.Dhl`)

**Status: Skeleton Implementation**

DHL Express integration for OrchardCore Commerce Shipping.

## Overview

This module provides a shipping provider implementation for DHL Express, supporting:

- ✅ Real-time rate quotes (skeleton)
- ✅ Shipment creation and label generation (skeleton)
- ✅ Tracking updates (skeleton)
- ❌ Full API integration (TODO)

## Installation

1. Enable the `OrchardCore.Commerce.Shipping` feature
2. Enable the `OrchardCore.Commerce.Shipping.Dhl` feature

## Configuration

### 1. Obtain DHL API Credentials

Sign up for DHL Developer Portal: https://developer.dhl.com/

You will need:
- **API Key**
- **API Secret**
- **Account Number**

### 2. Create Provider Connection

1. Navigate to **Content > New > Shipping Provider Connection**
2. Select **DHL** as provider
3. Enter credentials:
   - API Key
   - API Secret
   - Account Number
4. Toggle **Test Mode** for sandbox environment
5. Click **Test Connection**
6. Publish when healthy

### 3. Configure Shipping Methods

Create shipping methods using DHL services (e.g., "DHL Express Worldwide").

## Supported Services

The DHL provider supports all DHL Express services. Common examples:

- DHL Express Worldwide
- DHL Express 12:00
- DHL Express 9:00
- DHL Express Domestic

Service IDs are returned by the DHL API during rate requests.

## Implementation Status

⚠️ **This is a skeleton provider.** All interface methods return placeholders or throw `NotImplementedException`.

To complete implementation:

1. Install DHL SDK or use `HttpClient` for REST API calls
2. Implement `GetRatesAsync` with DHL Rate Request API
3. Implement `PurchaseShipmentAsync` with DHL Shipment Create API
4. Implement `GetTrackingAsync` with DHL Tracking API
5. Add proper error handling and logging
6. Write comprehensive unit tests

## API Reference

- [DHL Express MyDHL API Documentation](https://developer.dhl.com/api-reference/dhl-express-mydhl-api)
- [Rate Request](https://developer.dhl.com/api-reference/dhl-express-mydhl-api/rate-request)
- [Shipment Create](https://developer.dhl.com/api-reference/dhl-express-mydhl-api/shipment)
- [Tracking](https://developer.dhl.com/api-reference/dhl-express-mydhl-api/tracking)

## See Also

- [Commerce Shipping Overview](../Commerce.Shipping/README.md)
- [Shipping Provider Authoring Guide](../Commerce.Shipping/provider-authoring.md)
- [Full Shipping Design](../../../../../docs/new_module/OrchardCore-Commerce-Shipping-Design.md)
