using OrchardCore.ContentFields.Fields;
using OrchardCore.ContentManagement;

namespace OrchardCore.Commerce.Shipping.Models;

/// <summary>
/// Content part attached to Order storing selected shipping quote snapshot.
/// </summary>
public class ShippingOrderPart : ContentPart
{
    public TextField QuoteSnapshotJson { get; set; } = new();
    public TextField DestinationAddressJson { get; set; } = new();
    public TextField PickupPointJson { get; set; } = new();
    public TextField ExtensionDataJson { get; set; } = new();
    public TextField MetadataJson { get; set; } = new();
}
