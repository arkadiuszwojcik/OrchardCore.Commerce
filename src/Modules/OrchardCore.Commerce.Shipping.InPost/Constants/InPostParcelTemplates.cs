namespace OrchardCore.Commerce.Shipping.InPost.Constants;

/// <summary>
/// ShipX parcel dimension templates, grounded in the official InPost "Rozmiary i usługi dla przesyłek"
/// reference (see docs/Rozmiary i usługi dla przesyłek.md): small (8x38x64cm), medium (19x38x64cm),
/// large (41x38x64cm) - up to 25 kg, available for the locker services (and economy); xlarge (50x50x80cm,
/// up to 25 kg) is available only for <c>inpost_courier_c2c</c>. The letter_a/b/c templates are a separate
/// family used only by <c>inpost_letter_allegro</c>, capped at 10 kg; letter_c is a sum-of-dimensions
/// (&lt;= 160cm) template rather than a bounding box.
/// </summary>
public static class InPostParcelTemplates
{
    public const string Small = "small";
    public const string Medium = "medium";
    public const string Large = "large";
    public const string XLarge = "xlarge";
    public const string LetterA = "letter_a";
    public const string LetterB = "letter_b";
    public const string LetterC = "letter_c";
}
