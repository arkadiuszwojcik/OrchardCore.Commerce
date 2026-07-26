using OrchardCore.ContentFields.Fields;
using OrchardCore.ContentManagement;

namespace OrchardCore.Commerce.Shipping.Models;

/// <summary>
/// Content part representing a physical shipment (tracking, labels, fulfillment).
/// </summary>
public class ShipmentPart : ContentPart
{
    public TextField ShipmentId { get; set; } = new();
    public TextField OrderId { get; set; } = new();
    public TextField QuoteId { get; set; } = new();
    public TextField ProviderConnectionId { get; set; } = new();
    public TextField ServiceId { get; set; } = new();
    public TextField TrackingNumber { get; set; } = new();
    public TextField TrackingUrl { get; set; } = new();
    public TextField PurchaseStatus { get; set; } = new();
    public TextField FulfillmentStatus { get; set; } = new();
    public TextField TrackingStatus { get; set; } = new();
    public TextField LabelDocumentId { get; set; } = new();
    public TextField PackagesJson { get; set; } = new();
    public TextField OriginAddressJson { get; set; } = new();
    public TextField DestinationAddressJson { get; set; } = new();
    public TextField PickupPointJson { get; set; } = new();
    public TextField TrackingEventsJson { get; set; } = new();
    public TextField OperationsJson { get; set; } = new();
    public TextField MetadataJson { get; set; } = new();
}
