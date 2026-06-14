# Orchard Core Commerce - Shipping - InPost

This module provides the InPost shipping rate provider implementation for Orchard Core Commerce.

Current state:
- Uses live ShipX API calls to discover organization-enabled services and service metadata.
- Uses ShipX calculate endpoint to produce dynamic shipping prices per mapped service.
- Builds ShipX calculate payload from checkout address plus configurable sender/parcel defaults.
- Returns mapped Woo-compatible errors when ShipX responds with problem payloads.
