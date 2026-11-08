using Microsoft.AspNetCore.Mvc.ModelBinding;

namespace OrchardCore.Commerce.Shipping.Dhl.ViewModels;

public class DhlConnectionViewModel
{
    public string AccountNumber { get; set; } = string.Empty;

    /// <summary>
    /// Gets or sets the new API key. It is write-only: a blank value keeps the stored key.
    /// </summary>
    public string ApiKey { get; set; } = string.Empty;

    /// <summary>
    /// Gets or sets the new API secret. It is write-only: a blank value keeps the stored secret.
    /// </summary>
    public string ApiSecret { get; set; } = string.Empty;

    [BindNever]
    public bool HasApiKey { get; set; }

    [BindNever]
    public bool HasApiSecret { get; set; }
}
