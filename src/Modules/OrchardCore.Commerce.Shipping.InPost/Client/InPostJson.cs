using System.Text.Json;
using System.Text.Json.Serialization;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

internal static class InPostJson
{
    public static readonly JsonSerializerOptions Options = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.SnakeCaseLower,
        DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
    };
}
