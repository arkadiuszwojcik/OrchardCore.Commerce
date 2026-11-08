using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// Filters of the InPost Points search (<c>GET /v1/points</c>). Every property is optional; only the ones that are set
/// become query parameters, see <see cref="ToQuery"/>. Names, types and rules follow the Points documentation
/// ("Zasób Points (Paczkomat®, PaczkoPunkty)").
/// </summary>
public sealed record InPostPointSearch
{
    /// <summary>The maximum page size ShipX accepts for <see cref="PerPage"/>.</summary>
    public const int MaxPerPage = 500;

    /// <summary>The maximum <see cref="MaxDistance"/> in meters.</summary>
    public const double MaxDistanceLimit = 50000;

    /// <summary>Points with one of these names, e.g. <c>KRA010</c> (<c>name</c>).</summary>
    public IReadOnlyList<string>? Names { get; init; }

    /// <summary>Points of one of these types (<c>type</c>).</summary>
    public IReadOnlyList<InPostPointType>? Types { get; init; }

    /// <summary>Points that have all of these functions (<c>functions</c>).</summary>
    public IReadOnlyList<InPostPointFunction>? Functions { get; init; }

    /// <summary>Points with one of these partner IDs (<c>partner_id</c>); 0 is a parcel locker.</summary>
    public IReadOnlyList<int>? PartnerIds { get; init; }

    /// <summary>Only NEXT parcel lockers (<c>is_next</c>).</summary>
    public bool? IsNext { get; init; }

    /// <summary>Only points where payment is (not) available (<c>payment_available</c>).</summary>
    public bool? PaymentAvailable { get; init; }

    /// <summary>Points with one of these post codes (<c>post_code</c>).</summary>
    public IReadOnlyList<string>? PostCodes { get; init; }

    /// <summary>Points in one of these cities (<c>city</c>).</summary>
    public IReadOnlyList<string>? Cities { get; init; }

    /// <summary>Points in one of these provinces (<c>province</c>).</summary>
    public IReadOnlyList<string>? Provinces { get; init; }

    /// <summary>Points with one of these "virtuality" values (<c>virtual</c>).</summary>
    public IReadOnlyList<int>? Virtual { get; init; }

    /// <summary>
    /// Points updated on or after this day (<c>updated_from</c>). Without <see cref="UpdatedTo"/> it cannot be older
    /// than three days.
    /// </summary>
    public DateOnly? UpdatedFrom { get; init; }

    /// <summary>Points updated up to this day (<c>updated_to</c>); requires <see cref="UpdatedFrom"/>.</summary>
    public DateOnly? UpdatedTo { get; init; }

    /// <summary>Only points open 24/7 (<c>location_247</c>).</summary>
    public bool? Location247 { get; init; }

    /// <summary>Points with a controlled temperature, in °C (<c>supported_locker_temperatures</c>), e.g. 4 or 20.</summary>
    public int? SupportedLockerTemperature { get; init; }

    /// <summary>Finds the points closest to these coordinates (<c>relative_point</c>).</summary>
    public InPostGeoPoint? RelativePoint { get; init; }

    /// <summary>Finds the points closest to this post code (<c>relative_post_code</c>).</summary>
    public string? RelativePostCode { get; init; }

    /// <summary>
    /// The distance in meters from <see cref="RelativePoint"/> or <see cref="RelativePostCode"/> (<c>max_distance</c>).
    /// ShipX sorts by distance when it is set; the default is 10 km and the maximum <see cref="MaxDistanceLimit"/>.
    /// </summary>
    public double? MaxDistance { get; init; }

    /// <summary>How many points to return when searching around a location (<c>limit</c>).</summary>
    public int? Limit { get; init; }

    /// <summary>Attribute to sort by (<c>sort_by</c>). The default is <see cref="InPostPointSortBy.Name"/>.</summary>
    public InPostPointSortBy? SortBy { get; init; }

    /// <summary>Sort direction (<c>sort_order</c>). The default is <see cref="InPostSortOrder.Ascending"/>.</summary>
    public InPostSortOrder? SortOrder { get; init; }

    /// <summary>1-based page number (<c>page</c>).</summary>
    public int? Page { get; init; }

    /// <summary>Page size (<c>per_page</c>); the default is 25 and the maximum <see cref="MaxPerPage"/>.</summary>
    public int? PerPage { get; init; }

    /// <summary>
    /// Builds the query string parameters. Unset filters are left out, lists are comma separated and numbers use the
    /// invariant culture. Throws <see cref="InvalidOperationException"/> for combinations the API documents as invalid.
    /// </summary>
    public IDictionary<string, string> ToQuery()
    {
        if (UpdatedTo != null && UpdatedFrom == null)
        {
            throw new InvalidOperationException($"{nameof(UpdatedTo)} requires {nameof(UpdatedFrom)}.");
        }

        if (MaxDistance is <= 0 or > MaxDistanceLimit)
        {
            throw new InvalidOperationException($"{nameof(MaxDistance)} must be more than 0 and at most {MaxDistanceLimit} meters.");
        }

        if (PerPage is < 1 or > MaxPerPage)
        {
            throw new InvalidOperationException($"{nameof(PerPage)} must be between 1 and {MaxPerPage}.");
        }

        var query = new Dictionary<string, string>();

        InPostQuery.Add(query, "name", Names);
        InPostQuery.Add(query, "type", Types?.Select(InPostQuery.ToWireValue));
        InPostQuery.Add(query, "functions", Functions?.Select(InPostQuery.ToWireValue));
        InPostQuery.Add(query, "partner_id", PartnerIds);
        InPostQuery.Add(query, "is_next", IsNext);
        InPostQuery.Add(query, "payment_available", PaymentAvailable);
        InPostQuery.Add(query, "post_code", PostCodes);
        InPostQuery.Add(query, "city", Cities);
        InPostQuery.Add(query, "province", Provinces);
        InPostQuery.Add(query, "virtual", Virtual);
        InPostQuery.Add(query, "updated_from", UpdatedFrom?.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture));
        InPostQuery.Add(query, "updated_to", UpdatedTo?.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture));
        InPostQuery.Add(query, "location_247", Location247);
        InPostQuery.Add(query, "supported_locker_temperatures", SupportedLockerTemperature);
        InPostQuery.Add(
            query,
            "relative_point",
            RelativePoint is { } point
                ? string.Create(CultureInfo.InvariantCulture, $"{point.Latitude},{point.Longitude}")
                : null);
        InPostQuery.Add(query, "relative_post_code", RelativePostCode);
        InPostQuery.Add(query, "max_distance", MaxDistance);
        InPostQuery.Add(query, "limit", Limit);
        InPostQuery.Add(query, "sort_by", SortBy is { } sortBy ? InPostQuery.ToWireValue(sortBy) : null);
        InPostQuery.Add(query, "sort_order", SortOrder switch
        {
            null => null,
            InPostSortOrder.Ascending => "asc",
            InPostSortOrder.Descending => "desc",
            _ => throw new InvalidOperationException($"Unsupported sort order: {SortOrder}."),
        });
        InPostQuery.Add(query, "page", Page);
        InPostQuery.Add(query, "per_page", PerPage);

        return query;
    }
}
