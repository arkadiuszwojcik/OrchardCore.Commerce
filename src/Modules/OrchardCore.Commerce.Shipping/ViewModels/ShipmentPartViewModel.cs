namespace OrchardCore.Commerce.Shipping.ViewModels;

public class ShipmentPartViewModel
{
    public string ShipmentId { get; set; } = string.Empty;
    public string OrderId { get; set; } = string.Empty;
    public string TrackingNumber { get; set; } = string.Empty;
    public string TrackingUrl { get; set; } = string.Empty;
    public string PurchaseStatus { get; set; } = string.Empty;
    public string FulfillmentStatus { get; set; } = string.Empty;
    public string TrackingStatus { get; set; } = string.Empty;
    public bool HasLabel { get; set; }
    public string LabelDocumentId { get; set; } = string.Empty;
}
