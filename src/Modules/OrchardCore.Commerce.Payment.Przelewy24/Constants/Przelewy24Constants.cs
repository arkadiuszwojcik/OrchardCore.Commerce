using System.Text.Json;

namespace OrchardCore.Commerce.Payment.Przelewy24.Constants;

public static class Przelewy24Constants
{
    public static JsonSerializerOptions JsonSerializerOptions = new JsonSerializerOptions
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
    };
}
