namespace OrchardCore.Commerce.Shipping.ViewModels;

public class ShippingOrderPartViewModel
{
    public string ServiceName { get; set; } = string.Empty;
    public string TotalPrice { get; set; } = string.Empty;
    public string DestinationAddress { get; set; } = string.Empty;
    public string EstimatedDelivery { get; set; } = string.Empty;
    public bool HasPickupPoint { get; set; }
    public string PickupPointName { get; set; } = string.Empty;
}
