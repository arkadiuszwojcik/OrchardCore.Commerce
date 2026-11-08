using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Models;
using System;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

internal static class InPostConnection
{
    public const string ApiTokenKey = "ApiToken";

    internal const string ProductionBaseUrl = "https://api-shipx-pl.easypack24.net";
    internal const string SandboxBaseUrl = "https://sandbox-api-shipx-pl.easypack24.net";

    public static string GetBaseUrl(ShippingProviderConnectionContext context) =>
        context.IsTestMode ? SandboxBaseUrl : ProductionBaseUrl;

    public static string GetOrganizationId(ShippingProviderConnectionContext context) =>
        context.GetSettings<InPostConnectionSettings>()?.OrganizationId is { Length: > 0 } organizationId
            ? organizationId
            : throw new InvalidOperationException("The InPost connection is missing the required organization ID.");

    public static string GetApiToken(ShippingProviderConnectionContext context) =>
        context.Credentials.TryGetValue(ApiTokenKey, out var value) && !string.IsNullOrEmpty(value)
            ? value
            : throw new InvalidOperationException($"The InPost connection is missing the required '{ApiTokenKey}' credential.");
}
