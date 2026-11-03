using OrchardCore.Commerce.Shipping.Abstractions;
using System;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

internal static class InPostConnection
{
    private const string ProductionBaseUrl = "https://api-shipx-pl.easypack24.net";
    private const string SandboxBaseUrl = "https://sandbox-api-shipx-pl.easypack24.net";

    public static string GetBaseUrl(ShippingProviderConnectionContext context) =>
        IsSandbox(context) ? SandboxBaseUrl : ProductionBaseUrl;

    public static string GetOrganizationId(ShippingProviderConnectionContext context) =>
        GetRequiredCredential(context, "OrganizationId");

    public static string GetApiToken(ShippingProviderConnectionContext context) =>
        GetRequiredCredential(context, "ApiToken");

    private static bool IsSandbox(ShippingProviderConnectionContext context) =>
        context.IsTestMode ||
        (context.Metadata is not null &&
        context.Metadata.TryGetValue("IsTestMode", out var value) &&
        value switch
        {
            bool flag => flag,
            string text => bool.TryParse(text, out var parsed) && parsed,
            _ => false,
        });

    private static string GetRequiredCredential(ShippingProviderConnectionContext context, string key) =>
        context.Credentials.TryGetValue(key, out var value) && !string.IsNullOrEmpty(value)
            ? value
            : throw new InvalidOperationException($"The InPost connection is missing the required '{key}' credential.");
}
