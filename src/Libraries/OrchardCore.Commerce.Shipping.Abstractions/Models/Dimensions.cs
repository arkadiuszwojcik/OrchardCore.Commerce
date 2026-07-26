using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Represents physical dimensions (length × width × height) with a unit.
/// </summary>
public sealed record Dimensions(decimal Length, decimal Width, decimal Height, DimensionUnit Unit) : IComparable<Dimensions>
{
    /// <summary>
    /// Converts this dimension set to the target unit.
    /// </summary>
    public Dimensions ConvertTo(DimensionUnit targetUnit)
    {
        if (Unit == targetUnit) return this;

        return new Dimensions(
            ConvertValue(Length, Unit, targetUnit),
            ConvertValue(Width, Unit, targetUnit),
            ConvertValue(Height, Unit, targetUnit),
            targetUnit);
    }

    private static decimal ConvertValue(decimal value, DimensionUnit from, DimensionUnit to)
    {
        var centimeters = from switch
        {
            DimensionUnit.Centimeter => value,
            DimensionUnit.Meter => value * 100m,
            DimensionUnit.Inch => value * 2.54m,
            DimensionUnit.Foot => value * 30.48m,
            _ => throw new ArgumentOutOfRangeException(nameof(from)),
        };

        return to switch
        {
            DimensionUnit.Centimeter => centimeters,
            DimensionUnit.Meter => centimeters / 100m,
            DimensionUnit.Inch => centimeters / 2.54m,
            DimensionUnit.Foot => centimeters / 30.48m,
            _ => throw new ArgumentOutOfRangeException(nameof(to)),
        };
    }

    /// <summary>
    /// Calculates volume in cubic units.
    /// </summary>
    public decimal Volume => Length * Width * Height;

    public int CompareTo(Dimensions? other)
    {
        if (other is null) return 1;
        var normalized = other.ConvertTo(Unit);
        return Volume.CompareTo(normalized.Volume);
    }

    public override string ToString() => $"{Length}×{Width}×{Height} {Unit switch
    {
        DimensionUnit.Centimeter => "cm",
        DimensionUnit.Meter => "m",
        DimensionUnit.Inch => "in",
        DimensionUnit.Foot => "ft",
        _ => Unit.ToString(),
    }}";
}
