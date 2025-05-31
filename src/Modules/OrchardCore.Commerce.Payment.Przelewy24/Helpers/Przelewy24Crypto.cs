using System;
using System.Collections.Generic;
using System.Security.Cryptography;
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
        var jsonUtf8 = JsonSerializer.SerializeToUtf8Bytes(data, JsonSignOptions);
        byte[] hashBytes = SHA384.HashData(jsonUtf8);
        return BitConverter.ToString(hashBytes).Replace("-", "").ToLower();
    }
}
