using OrchardCore.ContentFields.Fields;
using OrchardCore.ContentManagement;

namespace OrchardCore.Commerce.Shipping.Models;

/// <summary>
/// Content part representing a shipping method (customer-facing option).
/// </summary>
public class ShippingMethodPart : ContentPart
{
    public TextField MethodId { get; set; } = new();
    public TextField DisplayName { get; set; } = new();
    public TextField Description { get; set; } = new();
    public TextField ProviderConnectionId { get; set; } = new();
    public TextField ServiceId { get; set; } = new();
    public TextField OriginId { get; set; } = new();
    public TextField MarkupFormulaJson { get; set; } = new();
    public TextField AvailabilityRulesJson { get; set; } = new();
    public BooleanField IsEnabled { get; set; } = new();
    public NumericField SortOrder { get; set; } = new();
    public TextField IconUrl { get; set; } = new();
    public TextField MetadataJson { get; set; } = new();
}
