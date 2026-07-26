using OrchardCore.ContentFields.Fields;
using OrchardCore.ContentManagement;

namespace OrchardCore.Commerce.Shipping.Models;

/// <summary>
/// Content part representing a shipping provider connection (credentials + settings for one carrier account).
/// </summary>
public class ShippingProviderConnectionPart : ContentPart
{
    public TextField ProviderId { get; set; } = new();
    public TextField ConnectionName { get; set; } = new();
    public TextField CredentialsJson { get; set; } = new();
    public TextField MetadataJson { get; set; } = new();
    public BooleanField IsEnabled { get; set; } = new();
    public BooleanField IsTestMode { get; set; } = new();
    public TextField LastHealthStatus { get; set; } = new();
    public TextField NormalizationProfileJson { get; set; } = new();
}
