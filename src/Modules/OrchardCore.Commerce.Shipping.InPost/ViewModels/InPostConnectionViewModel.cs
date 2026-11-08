using Microsoft.AspNetCore.Mvc.ModelBinding;

namespace OrchardCore.Commerce.Shipping.InPost.ViewModels;

public class InPostConnectionViewModel
{
    // Nullable so that MVC does not add an implicit "required" error with its own message; the driver validates them.
    public string? OrganizationId { get; set; }

    /// <summary>
    /// Gets or sets the new API token. It is write-only: a blank value keeps the stored token.
    /// </summary>
    public string? ApiToken { get; set; }

    [BindNever]
    public bool HasApiToken { get; set; }

    [BindNever]
    public string ConnectionId { get; set; } = string.Empty;

    /// <summary>
    /// Gets or sets whether the connection is in test mode. It is edited in the shared fields of the connection
    /// editor and only decides which API endpoint is displayed here.
    /// </summary>
    [BindNever]
    public bool IsTestMode { get; set; }

    [BindNever]
    public string SandboxBaseUrl { get; set; } = string.Empty;

    [BindNever]
    public string ProductionBaseUrl { get; set; } = string.Empty;
}
