using System;
using System.Collections.Generic;
using System.Text.Json;

namespace OrchardCore.Commerce.Payment.Przelewy24.Helpers;

public static class Przelewy24Crypto
{
    private static JsonSerializerOptions JsonSignOptions { get; } = new JsonSerializerOptions
    {
        Encoder = System.Text.Encodings.Web.JavaScriptEncoder.UnsafeRelaxedJsonEscaping,
    };

    public static string CalculateSign(IReadOnlyList<KeyValuePair<string, object>> data, string crc)
    {
        string json = JsonSerializer.Serialize(data, JsonSignOptions);

        using var sha384 = System.Security.Cryptography.SHA384.Create();
        byte[] hashBytes = sha384.ComputeHash(System.Text.Encoding.UTF8.GetBytes(json));
        return BitConverter.ToString(hashBytes).Replace("-", "").ToLower();
    }
}
