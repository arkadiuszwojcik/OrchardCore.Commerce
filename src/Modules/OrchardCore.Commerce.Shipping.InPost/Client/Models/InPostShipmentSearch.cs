using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// Filters of the ShipX shipment search (<c>GET /v1/organizations/{organizationId}/shipments</c>). Every property is
/// optional; only the ones that are set become query parameters, see <see cref="ToQuery"/>.
/// </summary>
public sealed record InPostShipmentSearch
{
    /// <summary>Shipments with one of these IDs (<c>id</c>).</summary>
    public IReadOnlyList<long>? Ids { get; init; }

    /// <summary>Shipments created on this day (<c>created_at</c>).</summary>
    public DateOnly? CreatedOn { get; init; }

    /// <summary>Shipments created at or after this time (<c>created_at_gteq</c>).</summary>
    public DateTimeOffset? CreatedFrom { get; init; }

    /// <summary>Shipments created at or before this time (<c>created_at_lteq</c>).</summary>
    public DateTimeOffset? CreatedTo { get; init; }

    /// <summary>Exact tracking number (<c>tracking_number</c>).</summary>
    public string? TrackingNumber { get; init; }

    /// <summary>Tracking number contains this text (<c>tracking_number_cont</c>).</summary>
    public string? TrackingNumberContains { get; init; }

    /// <summary>Shipment is in one of these statuses (<c>status</c>).</summary>
    public IReadOnlyList<string>? Statuses { get; init; }

    /// <summary>One of these services was purchased (<c>service</c>), e.g. <c>inpost_locker_standard</c>.</summary>
    public IReadOnlyList<string>? Services { get; init; }

    /// <summary>The service of one of these carriers was purchased (<c>carrier</c>).</summary>
    public IReadOnlyList<string>? Carriers { get; init; }

    public decimal? InsuranceAmountFrom { get; init; }
    public decimal? InsuranceAmountTo { get; init; }
    public decimal? CodAmountFrom { get; init; }
    public decimal? CodAmountTo { get; init; }

    public InPostPartySearch? Receiver { get; init; }
    public InPostPartySearch? Sender { get; init; }

    /// <summary>Shipment status is on ShipX's monitored list (<c>monitoring</c>).</summary>
    public bool? Monitoring { get; init; }

    /// <summary>Shipments sent by the broker with this ID (<c>external_customer_id</c>).</summary>
    public string? ExternalCustomerId { get; init; }

    /// <summary>One of these sending methods (<c>sending_method</c>), e.g. <c>parcel_locker</c>.</summary>
    public IReadOnlyList<string>? SendingMethods { get; init; }

    /// <summary>Only shipments with "only choice of offer" set and non-expired offers (<c>only_choice_active_offers</c>).</summary>
    public bool? OnlyChoiceActiveOffers { get; init; }

    /// <summary>Shipments with offers in this status (<c>offers_status</c>).</summary>
    public string? OffersStatus { get; init; }

    /// <summary>Attribute to sort by (<c>sort_by</c>). ShipX sorts by <see cref="InPostShipmentSortBy.CreatedAt"/> by default.</summary>
    public InPostShipmentSortBy? SortBy { get; init; }

    /// <summary>Sort direction (<c>sort_order</c>). ShipX sorts <see cref="InPostSortOrder.Descending"/> by default.</summary>
    public InPostSortOrder? SortOrder { get; init; }

    /// <summary>1-based page number (<c>page</c>).</summary>
    public int? Page { get; init; }

    /// <summary>Page size (<c>per_page</c>).</summary>
    public int? PerPage { get; init; }

    /// <summary>
    /// Builds the query string parameters. Unset filters are left out, lists are comma separated, times use ISO 8601
    /// and numbers use the invariant culture, as ShipX expects.
    /// </summary>
    public IDictionary<string, string> ToQuery()
    {
        var query = new Dictionary<string, string>();

        Add(query, "id", Ids?.Select(id => id.ToString(CultureInfo.InvariantCulture)));
        Add(query, "created_at", CreatedOn?.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture));
        Add(query, "created_at_gteq", CreatedFrom?.ToString("O", CultureInfo.InvariantCulture));
        Add(query, "created_at_lteq", CreatedTo?.ToString("O", CultureInfo.InvariantCulture));
        Add(query, "tracking_number", TrackingNumber);
        Add(query, "tracking_number_cont", TrackingNumberContains);
        Add(query, "status", Statuses);
        Add(query, "service", Services);
        Add(query, "carrier", Carriers);
        Add(query, "insurance_amount_gteq", InsuranceAmountFrom);
        Add(query, "insurance_amount_lteq", InsuranceAmountTo);
        Add(query, "cod_amount_gteq", CodAmountFrom);
        Add(query, "cod_amount_lteq", CodAmountTo);
        Receiver?.AddTo(query, "receiver");
        Sender?.AddTo(query, "sender");
        Add(query, "monitoring", Monitoring);
        Add(query, "external_customer_id", ExternalCustomerId);
        Add(query, "sending_method", SendingMethods);
        Add(query, "only_choice_active_offers", OnlyChoiceActiveOffers);
        Add(query, "offers_status", OffersStatus);
        Add(query, "sort_by", SortBy switch
        {
            null => null,
            InPostShipmentSortBy.Id => "id",
            InPostShipmentSortBy.CreatedAt => "created_at",
            InPostShipmentSortBy.TrackingNumber => "tracking_number",
            InPostShipmentSortBy.Service => "service",
            InPostShipmentSortBy.Status => "status",
            InPostShipmentSortBy.InsuranceAmount => "insurance_amount",
            InPostShipmentSortBy.CodAmount => "cod_amount",
            InPostShipmentSortBy.ExternalCustomerId => "external_customer_id",
            _ => throw new InvalidOperationException($"Unsupported shipment sort attribute: {SortBy}."),
        });
        Add(query, "sort_order", SortOrder switch
        {
            null => null,
            InPostSortOrder.Ascending => "asc",
            InPostSortOrder.Descending => "desc",
            _ => throw new InvalidOperationException($"Unsupported sort order: {SortOrder}."),
        });
        Add(query, "page", Page);
        Add(query, "per_page", PerPage);

        return query;
    }

    internal static void Add(IDictionary<string, string> query, string name, string? value)
    {
        if (!string.IsNullOrWhiteSpace(value)) query[name] = value;
    }

    internal static void Add(IDictionary<string, string> query, string name, IEnumerable<string>? values)
    {
        var joined = values == null ? null : string.Join(',', values.Where(value => !string.IsNullOrWhiteSpace(value)));
        Add(query, name, joined);
    }

    private static void Add(IDictionary<string, string> query, string name, bool? value) =>
        Add(query, name, value?.ToString().ToLowerInvariant());

    private static void Add<T>(IDictionary<string, string> query, string name, T? value)
        where T : struct, IFormattable =>
        Add(query, name, value?.ToString(null, CultureInfo.InvariantCulture));
}

/// <summary>
/// Filters on one party (receiver or sender) of a shipment. The parameter names are the property names prefixed with
/// <c>receiver_</c> or <c>sender_</c>. Name, address, post code and e-mail match by prefix; city, country code and
/// phone match exactly.
/// </summary>
public sealed record InPostPartySearch
{
    /// <summary>First name, last name or company name starts with this text (<c>name</c>).</summary>
    public string? Name { get; init; }

    /// <summary>Street or <c>line1</c> starts with this text (<c>address</c>).</summary>
    public string? Address { get; init; }

    public string? City { get; init; }
    public string? PostCode { get; init; }
    public string? CountryCode { get; init; }
    public string? Phone { get; init; }
    public string? Email { get; init; }

    internal void AddTo(IDictionary<string, string> query, string prefix)
    {
        InPostShipmentSearch.Add(query, $"{prefix}_name", Name);
        InPostShipmentSearch.Add(query, $"{prefix}_address", Address);
        InPostShipmentSearch.Add(query, $"{prefix}_city", City);
        InPostShipmentSearch.Add(query, $"{prefix}_post_code", PostCode);
        InPostShipmentSearch.Add(query, $"{prefix}_country_code", CountryCode);
        InPostShipmentSearch.Add(query, $"{prefix}_phone", Phone);
        InPostShipmentSearch.Add(query, $"{prefix}_email", Email);
    }
}
