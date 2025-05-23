using Microsoft.AspNetCore.DataProtection;
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Payment.Przelewy24.Extensions;
using System;
using System.ComponentModel.DataAnnotations;

namespace OrchardCore.Commerce.Payment.Przelewy24.Models;

public class Przelewy24Settings
{
    [Required]
    public string BaseAddress { get; set; } = "https://secure.przelewy24.pl/";

    [Required]
    public string ProjectId { get; set; }

    public string ApiKey { get; set; }

    public string CrcKeySecret { get; set; }

    public string DecryptCrcKey(IDataProtectionProvider dataProtectionProvider, ILogger logger) =>
        CrcKeySecret.DecryptSecretString(dataProtectionProvider, logger);

    public void CopyTo(Przelewy24Settings target)
    {
        if (Uri.TryCreate(BaseAddress, UriKind.Absolute, out var baseUri)) target.BaseAddress = baseUri.AbsoluteUri;
        if (!string.IsNullOrWhiteSpace(ProjectId)) target.ProjectId = ProjectId.Trim();
        if (!string.IsNullOrWhiteSpace(ApiKey)) target.ApiKey = ApiKey.Trim();
    }
}
