#nullable enable

using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.ContentManagement.Display.Models;
using OrchardCore.DisplayManagement.Views;
using System.Text.Json;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShippingOrderPartDisplayDriver : ContentPartDisplayDriver<ShippingOrderPart>
{
    private static readonly JsonSerializerOptions _jsonOptions = new(JsonSerializerDefaults.Web);

    public override IDisplayResult Display(ShippingOrderPart part, BuildPartDisplayContext context) =>
        Initialize<ShippingOrderPartViewModel>(GetDisplayShapeType(context), vm =>
        {
            if (string.IsNullOrWhiteSpace(part.QuoteSnapshotJson.Text)) return;

            try
            {
                var snap = JsonSerializer.Deserialize<QuoteSnap>(part.QuoteSnapshotJson.Text, _jsonOptions);
                if (snap == null) return;
                vm.ServiceName = snap.ServiceName ?? string.Empty;
                vm.TotalPrice = snap.TotalPrice ?? string.Empty;
                vm.EstimatedDelivery = snap.EstimatedDelivery ?? string.Empty;
            }
            catch { }

            if (!string.IsNullOrWhiteSpace(part.DestinationAddressJson.Text))
            {
                try
                {
                    var addr = JsonSerializer.Deserialize<AddrSnap>(part.DestinationAddressJson.Text, _jsonOptions);
                    if (addr != null)
                        vm.DestinationAddress = $"{addr.Line1}, {addr.City}, {addr.CountryCode}".Trim(' ', ',');
                }
                catch { }
            }

            if (!string.IsNullOrWhiteSpace(part.PickupPointJson.Text))
            {
                try
                {
                    var pp = JsonSerializer.Deserialize<PpSnap>(part.PickupPointJson.Text, _jsonOptions);
                    if (pp?.LocationName != null)
                    {
                        vm.HasPickupPoint = true;
                        vm.PickupPointName = pp.LocationName;
                    }
                }
                catch { }
            }
        })
        .Location("Detail", "Content:20")
        .Location("Summary", "Meta");

    private sealed class QuoteSnap
    {
        public string? ServiceName { get; set; }
        public string? TotalPrice { get; set; }
        public string? EstimatedDelivery { get; set; }
    }

    private sealed class AddrSnap
    {
        public string? Line1 { get; set; }
        public string? City { get; set; }
        public string? CountryCode { get; set; }
    }

    private sealed class PpSnap
    {
        public string? LocationName { get; set; }
    }
}
