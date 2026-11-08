namespace OrchardCore.Commerce.Shipping.InPost.Models;

/// <summary>
/// The non-secret InPost settings of a connection. They are stored in the connection's properties under the name of
/// this type. The API token is a secret and is kept in the secret store instead.
/// </summary>
public class InPostConnectionSettings
{
    public string? OrganizationId { get; set; }
}
