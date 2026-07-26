namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Rule for matching postal codes (used in shipping method availability).
/// </summary>
public sealed record PostalCodeRule(
    string Pattern,
    PostalCodeMatchType MatchType = PostalCodeMatchType.Exact)
{
    /// <summary>
    /// Checks if the given postal code matches this rule.
    /// </summary>
    public bool Matches(string postalCode)
    {
        if (string.IsNullOrWhiteSpace(postalCode)) return false;

        var normalized = postalCode.Trim().ToUpperInvariant();
        var patternNormalized = Pattern.Trim().ToUpperInvariant();

        return MatchType switch
        {
            PostalCodeMatchType.Exact => normalized == patternNormalized,
            PostalCodeMatchType.Prefix => normalized.StartsWith(patternNormalized),
            PostalCodeMatchType.Wildcard => System.Text.RegularExpressions.Regex.IsMatch(
                normalized,
                "^" + System.Text.RegularExpressions.Regex.Escape(patternNormalized).Replace("\\*", ".*") + "$"),
            PostalCodeMatchType.Range => MatchRange(normalized, patternNormalized),
            _ => false,
        };
    }

    private static bool MatchRange(string value, string pattern)
    {
        var parts = pattern.Split('-');
        if (parts.Length != 2) return false;

        var start = parts[0].Trim();
        var end = parts[1].Trim();

        return string.Compare(value, start, System.StringComparison.Ordinal) >= 0 &&
               string.Compare(value, end, System.StringComparison.Ordinal) <= 0;
    }
}
