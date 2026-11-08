namespace OrchardCore.Commerce.Shipping.Dhl.Models;

/// <summary>
/// The non-secret DHL settings of a connection. They are stored in the connection's properties under the name of
/// this type. The API key and secret are secrets and are kept in the secret store instead.
/// </summary>
public class DhlConnectionSettings
{
    public string AccountNumber { get; set; }
}
