namespace OrchardCore.Commerce.Shipping.WooCompatibility.Options;

public class WooShippingCompatibilityApiOptions
{
    public const string SectionName = "OrchardCore:Commerce:Shipping:WooCompatibilityApi";

    public string? WebhookToken { get; set; }
}