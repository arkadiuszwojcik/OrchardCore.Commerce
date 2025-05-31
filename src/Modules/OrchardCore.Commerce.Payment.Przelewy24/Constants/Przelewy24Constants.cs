using System.Text.Json;

namespace OrchardCore.Commerce.Payment.Przelewy24.Constants;

public static class Przelewy24Constants
{
    public static class Features
    {
        public const string Przelewy24 = "OrchardCore.Commerce.Payment.Przelewy24";
    }

    public static JsonSerializerOptions JsonSerializerOptions = new JsonSerializerOptions
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
    };
}
