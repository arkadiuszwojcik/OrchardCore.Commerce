using System.Text.Json;
using System.Text.Json.Nodes;

namespace OrchardCore.Commerce.Shipping.Abstractions;

public static class ShippingProviderConnectionContextExtensions
{
    /// <summary>
    /// Reads the provider-specific settings object that the provider's connection editor stored on the connection.
    /// Settings are keyed by the name of their type, so a provider reads back the same class its editor wrote.
    /// </summary>
    public static T? GetSettings<T>(this ShippingProviderConnectionContext context)
        where T : class
    {
        if (context.Metadata is null || !context.Metadata.TryGetValue(typeof(T).Name, out var value)) return null;

        return value switch
        {
            T typed => typed,
            JsonNode node => node.Deserialize<T>(),
            JsonElement element => element.Deserialize<T>(),
            _ => null,
        };
    }
}
