using System;
using System.Net;

namespace OrchardCore.Commerce.Shipping.InPost.Client;

/// <summary>
/// Thrown when a ShipX API call returns a non-success HTTP status or an in-band error envelope
/// (<c>{"status": ..., "key": ..., "error": ...}</c>), a pattern grounded in <c>ShipXClient::getExceptionByErrorCode</c>
/// (PrestaShop plugin).
/// </summary>
public sealed class InPostApiException : Exception
{
    public HttpStatusCode StatusCode { get; }
    public string? ErrorCode { get; }

    public InPostApiException(HttpStatusCode statusCode, string? errorCode, string message)
        : base(message)
    {
        StatusCode = statusCode;
        ErrorCode = errorCode;
    }
}
