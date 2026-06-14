using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.WooCompatibility.Endpoints.Models;

public sealed record WooApiErrorResponse(
    string Code,
    string Message,
    WooApiErrorData Data);

public sealed record WooApiErrorData(
    int Status,
    string? Provider = null,
    string? ProviderCode = null,
    string? ProviderType = null,
    string? ProviderDetail = null,
    string? CorrelationId = null,
    IReadOnlyDictionary<string, string[]>? Errors = null);