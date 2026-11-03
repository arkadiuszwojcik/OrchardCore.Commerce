using System;

namespace OrchardCore.Commerce.Shipping.Abstractions;

/// <summary>
/// Thrown by a provider when the carrier definitely rejected a request (for example validation or authorization
/// failure), so no shipment exists and the operation is safe to retry. Any other exception thrown from a purchase
/// call is treated by the core as an uncertain outcome.
/// </summary>
public class ShippingProviderRejectedException : Exception
{
    public ShippingProviderRejectedException(string message, Exception? innerException = null)
        : base(message, innerException)
    {
    }
}
