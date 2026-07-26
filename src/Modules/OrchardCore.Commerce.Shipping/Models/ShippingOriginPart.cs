using OrchardCore.ContentFields.Fields;
using OrchardCore.ContentManagement;

namespace OrchardCore.Commerce.Shipping.Models;

/// <summary>
/// Content part representing a shipping origin address (warehouse, store).
/// </summary>
public class ShippingOriginPart : ContentPart
{
    public TextField OriginId { get; set; } = new();
    public TextField OriginName { get; set; } = new();
    public TextField AddressJson { get; set; } = new();
    public BooleanField IsDefault { get; set; } = new();
    public TextField MetadataJson { get; set; } = new();
}
