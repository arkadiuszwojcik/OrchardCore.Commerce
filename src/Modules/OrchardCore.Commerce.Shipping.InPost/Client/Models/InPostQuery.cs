using System;
using System.Collections.Generic;
using System.Globalization;
using System.Linq;
using System.Text.Json;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// Shared rules for turning search models into ShipX / Points query string parameters: unset values are left out,
/// lists are comma separated, booleans are lowercase and numbers use the invariant culture.
/// </summary>
internal static class InPostQuery
{
    public static void Add(IDictionary<string, string> query, string name, string? value)
    {
        if (!string.IsNullOrWhiteSpace(value)) query[name] = value;
    }

    public static void Add(IDictionary<string, string> query, string name, IEnumerable<string>? values)
    {
        var joined = values == null ? null : string.Join(',', values.Where(value => !string.IsNullOrWhiteSpace(value)));
        Add(query, name, joined);
    }

    public static void Add(IDictionary<string, string> query, string name, bool? value) =>
        Add(query, name, value?.ToString().ToLowerInvariant());

    public static void Add<T>(IDictionary<string, string> query, string name, T? value)
        where T : struct, IFormattable =>
        Add(query, name, value?.ToString(null, CultureInfo.InvariantCulture));

    public static void Add<T>(IDictionary<string, string> query, string name, IEnumerable<T>? values)
        where T : struct, IFormattable =>
        Add(query, name, values?.Select(value => value.ToString(null, CultureInfo.InvariantCulture)));

    /// <summary>
    /// The wire value of an enum member that is named after it in PascalCase, e.g. <c>ParcelLockerSuperpop</c> is
    /// <c>parcel_locker_superpop</c>.
    /// </summary>
    public static string ToWireValue<TEnum>(TEnum value)
        where TEnum : struct, Enum =>
        JsonNamingPolicy.SnakeCaseLower.ConvertName(value.ToString());
}
