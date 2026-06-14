using System;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Exceptions;

public class ShippingProviderException : Exception
{
    public string Provider { get; }

    public string Code { get; }

    public int? Status { get; }

    public string? ProviderType { get; }

    public string? ProviderDetail { get; }

    public string? CorrelationId { get; }

    public IReadOnlyDictionary<string, string[]>? Errors { get; }

    public ShippingProviderException(
        string provider,
        string code,
        string message,
        int? status = null,
        string? providerType = null,
        string? providerDetail = null,
        string? correlationId = null,
        IReadOnlyDictionary<string, string[]>? errors = null,
        Exception? innerException = null)
        : base(message, innerException)
    {
        Provider = provider;
        Code = code;
        Status = status;
        ProviderType = providerType;
        ProviderDetail = providerDetail;
        CorrelationId = correlationId;
        Errors = errors;
    }
}