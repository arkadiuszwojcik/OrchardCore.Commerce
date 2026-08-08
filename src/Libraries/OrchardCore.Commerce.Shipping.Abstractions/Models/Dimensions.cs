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
        var millimeters = from switch
        {
            DimensionUnit.Millimeter => value,
            DimensionUnit.Centimeter => value * 10m,
            DimensionUnit.Meter => value * 1000m,
            DimensionUnit.Inch => value * 25.4m,
            DimensionUnit.Foot => value * 304.8m,
            _ => throw new ArgumentOutOfRangeException(nameof(from)),
        };

        return to switch
        {
            DimensionUnit.Millimeter => millimeters,
            DimensionUnit.Centimeter => millimeters / 10m,
            DimensionUnit.Meter => millimeters / 1000m,
            DimensionUnit.Inch => millimeters / 25.4m,
            DimensionUnit.Foot => millimeters / 304.8m,
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
        DimensionUnit.Millimeter => "mm",
        DimensionUnit.Centimeter => "cm",
        DimensionUnit.Meter => "m",
        DimensionUnit.Inch => "in",
        DimensionUnit.Foot => "ft",
        _ => Unit.ToString(),
    }}";
}
