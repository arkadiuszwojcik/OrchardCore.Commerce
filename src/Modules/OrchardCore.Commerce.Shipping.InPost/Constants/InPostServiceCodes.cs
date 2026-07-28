namespace OrchardCore.Commerce.Shipping.InPost.Constants;

/// <summary>
/// Well-known InPost ShipX service codes, as used by the official InPost WooCommerce and PrestaShop
/// plugins. These identify the product/service offered to the customer (locker, courier, pallet, etc.).
/// </summary>
public static class InPostServiceCodes
{
    /// <summary>
    /// Parcel locker (Paczkomat) shipment, standard/weekend collection.
    /// </summary>
    public const string LockerStandard = "inpost_locker_standard";

    /// <summary>
    /// Parcel locker (Paczkomat) shipment, economy (slower, cheaper) variant.
    /// </summary>
    public const string LockerEconomy = "inpost_locker_economy";

    /// <summary>
    /// Allegro InPost Parcel Lockers shipment.
    /// </summary>
    public const string LockerAllegro = "inpost_locker_allegro";

    /// <summary>
    /// Pass-thru locker shipment.
    /// </summary>
    public const string LockerPassThru = "inpost_locker_pass_thru";

    /// <summary>
    /// Standard courier (door-to-door) shipment.
    /// </summary>
    public const string CourierStandard = "inpost_courier_standard";

    /// <summary>
    /// Customer-to-customer courier shipment.
    /// </summary>
    public const string CourierC2C = "inpost_courier_c2c";

    /// <summary>
    /// Courier shipment guaranteed for delivery until 10:00.
    /// </summary>
    public const string CourierExpress1000 = "inpost_courier_express_1000";

    /// <summary>
    /// Courier shipment guaranteed for delivery until 12:00.
    /// </summary>
    public const string CourierExpress1200 = "inpost_courier_express_1200";

    /// <summary>
    /// Courier shipment guaranteed for delivery until 17:00.
    /// </summary>
    public const string CourierExpress1700 = "inpost_courier_express_1700";

    /// <summary>
    /// Standard pallet courier shipment.
    /// </summary>
    public const string CourierPalette = "inpost_courier_palette";

    /// <summary>
    /// SmartCourier shipment for alcohol (age-restricted) deliveries.
    /// </summary>
    public const string CourierAlcohol = "inpost_courier_alcohol";

    /// <summary>
    /// Local standard courier shipment.
    /// </summary>
    public const string CourierLocalStandard = "inpost_courier_local_standard";

    /// <summary>
    /// Local express courier shipment.
    /// </summary>
    public const string CourierLocalExpress = "inpost_courier_local_express";

    /// <summary>
    /// Local super-express courier shipment.
    /// </summary>
    public const string CourierLocalSuperExpress = "inpost_courier_local_super_express";

    /// <summary>
    /// Allegro InPost courier shipment.
    /// </summary>
    public const string CourierAllegro = "inpost_courier_allegro";

    /// <summary>
    /// Allegro InPost registered mail shipment.
    /// </summary>
    public const string LetterAllegro = "inpost_letter_allegro";

    /// <summary>
    /// InPost e-commerce letter/parcel shipment.
    /// </summary>
    public const string LetterEcommerce = "inpost_letter_ecommerce";
}
