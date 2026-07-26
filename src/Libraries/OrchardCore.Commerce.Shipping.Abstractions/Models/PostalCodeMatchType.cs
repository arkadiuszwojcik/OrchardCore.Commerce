namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// How postal code patterns should be matched.
/// </summary>
public enum PostalCodeMatchType
{
    /// <summary>
    /// Exact match required.
    /// </summary>
    Exact,

    /// <summary>
    /// Prefix match (e.g., "123" matches "12345").
    /// </summary>
    Prefix,

    /// <summary>
    /// Range match (e.g., "10000-20000").
    /// </summary>
    Range,

    /// <summary>
    /// Wildcard match (e.g., "12*").
    /// </summary>
    Wildcard,
}
