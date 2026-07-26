using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Capabilities supported by a shipping provider (flags enum).
/// </summary>
[Flags]
public enum ShippingProviderCapabilities
{
    /// <summary>
    /// No capabilities.
    /// </summary>
    None = 0,

    /// <summary>
    /// Provider supports real-time rate quotes.
    /// </summary>
    RateQuotes = 1 << 0,

    /// <summary>
    /// Provider supports purchasing shipment labels.
    /// </summary>
    PurchaseLabels = 1 << 1,

    /// <summary>
    /// Provider supports tracking shipments.
    /// </summary>
    Tracking = 1 << 2,

    /// <summary>
    /// Provider supports address validation.
    /// </summary>
    AddressValidation = 1 << 3,

    /// <summary>
    /// Provider supports pickup/drop-off point (PUDO) queries.
    /// </summary>
    PickupPoints = 1 << 4,

    /// <summary>
    /// Provider supports shipment cancellation/voiding.
    /// </summary>
    CancelShipment = 1 << 5,

    /// <summary>
    /// Provider supports customs declarations for international shipping.
    /// </summary>
    CustomsDeclarations = 1 << 6,

    /// <summary>
    /// Provider supports insurance quotes and purchase.
    /// </summary>
    Insurance = 1 << 7,
}
