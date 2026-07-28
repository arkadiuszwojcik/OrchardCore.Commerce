namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// ShipX shipment <c>custom_attributes</c>, grounded in the PHP plugins' shipment payloads:
/// <c>sending_method</c> (see <see cref="Constants.InPostSendingMethods"/>), <c>target_point</c> (locker/POP id)
/// and <c>dropoff_point</c> (drop-off locker id).
/// </summary>
public sealed record InPostCustomAttributes(string SendingMethod, string? TargetPoint = null, string? DropoffPoint = null);
