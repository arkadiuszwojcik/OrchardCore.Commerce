namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// The kinds of points a search can filter by (the <c>type</c> values of the Points documentation). The wire value is
/// the snake_case name, e.g. <see cref="ParcelLockerSuperpop"/> is <c>parcel_locker_superpop</c>.
/// </summary>
public enum InPostPointType
{
    /// <summary>Physical parcel lockers and PaczkoPunkty a parcel locker shipment can be sent to.</summary>
    ParcelLocker,

    /// <summary>PaczkoPunkty.</summary>
    Pop,

    /// <summary>Only the physical parcel lockers.</summary>
    ParcelLockerOnly,

    /// <summary>PaczkoPunkty a parcel locker shipment can be sent to.</summary>
    ParcelLockerSuperpop,
}

/// <summary>
/// The functions a point can offer (the <c>functions</c> values of the Points documentation). The wire value is the
/// snake_case name, e.g. <see cref="ParcelReverseReturnSend"/> is <c>parcel_reverse_return_send</c>.
/// </summary>
public enum InPostPointFunction
{
    Parcel,
    ParcelSend,
    ParcelCollect,
    ParcelReverseReturnSend,
    StandardLetterCollect,
    StandardLetterSend,
    AllegroParcelCollect,
    AllegroParcelSend,
    AllegroParcelReverseReturnSend,
    AllegroLetterCollect,
    AllegroLetterSend,
    AllegroLetterReverseReturnSend,
    AllegroCourierCollect,
    AllegroCourierSend,
    AllegroCourierReverseReturnSend,
    StandardCourierCollect,
    StandardCourierSend,
    StandardCourierReverseReturnSend,
    AirOnAirport,
    AirOutsideAirport,
    CoolParcelCollect,
    Laundry,
    Avizo,
}

/// <summary>The attributes a points search can be sorted by (the <c>sort_by</c> values of the Points documentation).</summary>
public enum InPostPointSortBy
{
    Name,

    /// <summary>Needs <see cref="InPostPointSearch.RelativePoint"/> or <see cref="InPostPointSearch.RelativePostCode"/>.</summary>
    DistanceToRelativePoint,

    Status,
}
