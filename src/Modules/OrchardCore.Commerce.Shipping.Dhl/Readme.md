# OrchardCore.Commerce.Shipping.Dhl

**Status: Skeleton Implementation**

DHL Express shipping provider for OrchardCore Commerce.

## Implementation Status

✅ Provider registration and descriptor
✅ Interface implementations (IShippingRateProvider, IShipmentProvider, IShippingTrackingProvider)
❌ Actual DHL API integration (all methods return placeholders)

## TODO

1. **Install DHL SDK** or use HttpClient for REST API
2. **Implement GetRatesAsync**: Call DHL Rate Request API with package/address data
3. **Implement PurchaseShipmentAsync**: Call DHL Shipment Create API, retrieve labels
4. **Implement GetTrackingAsync**: Call DHL Tracking API
5. **Add credential encryption** for API keys
6. **Add error handling** and logging
7. **Write unit tests** with mocked DHL responses
8. **Add admin UI** for DHL-specific settings (preferred services, insurance options)

## API Reference

- [DHL Express MyDHL API Documentation](https://developer.dhl.com/api-reference/dhl-express-mydhl-api)
- Required credentials: `ApiKey`, `ApiSecret`, `AccountNumber`

## Usage

1. Enable the `OrchardCore.Commerce.Shipping.Dhl` feature
2. Create a "Shipping Provider Connection" content item
3. Select "DHL" as provider
4. Enter API credentials
5. Configure shipping methods using DHL services

---

**Note:** This is a skeleton. Real implementation requires DHL API integration, proper error handling, and comprehensive testing.
