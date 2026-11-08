using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// One page of a points search. The counters come from the collection envelope and are null when the response was a
/// bare array.
/// </summary>
public sealed record InPostPointPage(
    IReadOnlyList<InPostPoint> Items,
    int? Count,
    int? Page,
    int? PerPage,
    int? TotalPages);
