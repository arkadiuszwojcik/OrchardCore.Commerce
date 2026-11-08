using System.Collections.Generic;
using System.Text.Json.Serialization;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// A location of the InPost Points resource (a parcel locker, a PaczkoPunkt, a branch). The properties follow the
/// documented attributes and the sandbox responses (snake_case on the wire); attributes that are not needed here
/// (<c>href</c>, <c>payment_type</c>, <c>apm_doubled</c>, <c>operating_hours_extended</c>, <c>agency</c> and a few
/// internal flags) are not modeled. <see cref="Type"/>, <see cref="Status"/> and <see cref="Functions"/> stay strings
/// so values InPost adds later do not break reading; <see cref="InPostPointType"/> and
/// <see cref="InPostPointFunction"/> list the documented ones for searching.
/// </summary>
public sealed record InPostPoint(
    string Name,
    string? DisplayName,
    IReadOnlyList<string>? Type,
    string? Status,
    InPostGeoPoint? Location,
    string? LocationType,
    string? LocationDescription,
    [property: JsonPropertyName("location_description_1")] string? LocationDescription1,
    [property: JsonPropertyName("location_description_2")] string? LocationDescription2,
    int? Distance,
    string? OpeningHours,
    InPostPointAddress? Address,
    InPostPointAddressDetails? AddressDetails,
    string? PhoneNumber,
    string? PaymentPointDescr,
    IReadOnlyList<string>? Functions,
    int? PartnerId,
    bool? IsNext,
    bool? PaymentAvailable,
    [property: JsonNumberHandling(JsonNumberHandling.AllowReadingFromString)] int? Virtual,
    IReadOnlyList<string>? RecommendedLowInterestBoxMachinesList,
    [property: JsonPropertyName("location_247")] bool? Location247,
    bool? EasyAccessZone,
    string? ImageUrl,
    string? AirIndexLevel,
    string? PhysicalType,
    string? PhysicalTypeMapped,
    string? PhysicalTypeDescription,
    IReadOnlyList<int>? SupportedLockerTemperatures);

/// <summary>A geographic position in degrees.</summary>
public sealed record InPostGeoPoint(double Latitude, double Longitude);

/// <summary>The display address of a point: a street line and a "post code city" line.</summary>
public sealed record InPostPointAddress(string? Line1, string? Line2);

/// <summary>The structured address of a point.</summary>
public sealed record InPostPointAddressDetails(
    string? City,
    string? Province,
    string? PostCode,
    string? Street,
    string? BuildingNumber,
    string? FlatNumber);
