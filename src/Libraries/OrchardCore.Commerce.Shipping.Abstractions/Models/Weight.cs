using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Represents a weight value with a unit.
/// </summary>
public sealed record Weight(decimal Value, WeightUnit Unit) : IComparable<Weight>
{
    /// <summary>
    /// Converts this weight to the target unit.
    /// </summary>
    public Weight ConvertTo(WeightUnit targetUnit)
    {
        if (Unit == targetUnit) return this;

        var grams = Unit switch
        {
            WeightUnit.Gram => Value,
            WeightUnit.Kilogram => Value * 1000m,
            WeightUnit.Ounce => Value * 28.349523125m,
            WeightUnit.Pound => Value * 453.59237m,
            _ => throw new ArgumentOutOfRangeException(nameof(Unit)),
        };

        var result = targetUnit switch
        {
            WeightUnit.Gram => grams,
            WeightUnit.Kilogram => grams / 1000m,
            WeightUnit.Ounce => grams / 28.349523125m,
            WeightUnit.Pound => grams / 453.59237m,
            _ => throw new ArgumentOutOfRangeException(nameof(targetUnit)),
        };

        return new Weight(result, targetUnit);
    }

    public int CompareTo(Weight? other)
    {
        if (other is null) return 1;
        var normalized = other.ConvertTo(Unit);
        return Value.CompareTo(normalized.Value);
    }

    public static Weight operator +(Weight left, Weight right) =>
        new(left.Value + right.ConvertTo(left.Unit).Value, left.Unit);

    public override string ToString() => $"{Value} {Unit switch
    {
        WeightUnit.Gram => "g",
        WeightUnit.Kilogram => "kg",
        WeightUnit.Ounce => "oz",
        WeightUnit.Pound => "lb",
        _ => Unit.ToString(),
    }}";
}
