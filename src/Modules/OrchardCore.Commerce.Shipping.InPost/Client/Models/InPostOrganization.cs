namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// Subset of the ShipX organization resource fields, grounded in <c>Organization.php</c>: <c>id</c>, <c>name</c>.
/// </summary>
public sealed record InPostOrganization(long? Id, string? Name);
