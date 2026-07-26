#nullable enable

using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.ContentManagement.Display.ContentDisplay;
using OrchardCore.ContentManagement.Display.Models;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using System.Text.Json;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class ShippingOriginPartDisplayDriver : ContentPartDisplayDriver<ShippingOriginPart>
{
    private static readonly JsonSerializerOptions _jsonOptions = new(JsonSerializerDefaults.Web);

    public override IDisplayResult Edit(ShippingOriginPart part, BuildPartEditorContext context) =>
        Initialize<ShippingOriginPartViewModel>(GetEditorShapeType(context), vm =>
        {
            vm.OriginName = part.OriginName.Text ?? string.Empty;
            vm.IsDefault = part.IsDefault.Value;

            if (string.IsNullOrWhiteSpace(part.AddressJson.Text)) return;

            try
            {
                var addr = JsonSerializer.Deserialize<AddressData>(part.AddressJson.Text, _jsonOptions);
                if (addr == null) return;
                vm.Name = addr.Name ?? string.Empty;
                vm.Company = addr.Company ?? string.Empty;
                vm.Line1 = addr.Line1 ?? string.Empty;
                vm.Line2 = addr.Line2 ?? string.Empty;
                vm.City = addr.City ?? string.Empty;
                vm.Province = addr.Province ?? string.Empty;
                vm.PostalCode = addr.PostalCode ?? string.Empty;
                vm.CountryCode = addr.CountryCode ?? string.Empty;
                vm.Phone = addr.Phone ?? string.Empty;
            }
            catch { /* ignore malformed JSON */ }
        });

    public override async Task<IDisplayResult> UpdateAsync(ShippingOriginPart part, UpdatePartEditorContext context)
    {
        var vm = await context.CreateModelAsync<ShippingOriginPartViewModel>(Prefix);

        part.OriginName.Text = vm.OriginName;
        part.IsDefault.Value = vm.IsDefault;
        part.AddressJson.Text = JsonSerializer.Serialize(new AddressData
        {
            Name = vm.Name,
            Company = vm.Company,
            Line1 = vm.Line1,
            Line2 = vm.Line2,
            City = vm.City,
            Province = vm.Province,
            PostalCode = vm.PostalCode,
            CountryCode = vm.CountryCode,
            Phone = vm.Phone,
        }, _jsonOptions);

        return await EditAsync(part, context);
    }

    private sealed class AddressData
    {
        public string? Name { get; set; }
        public string? Company { get; set; }
        public string? Line1 { get; set; }
        public string? Line2 { get; set; }
        public string? City { get; set; }
        public string? Province { get; set; }
        public string? PostalCode { get; set; }
        public string? CountryCode { get; set; }
        public string? Phone { get; set; }
    }
}
