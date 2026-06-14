namespace OrchardCore.Commerce.Shipping.InPost.Options;

using System;
using System.ComponentModel.DataAnnotations;

public class InPostShippingOptions
{
    public const string SectionName = "OrchardCore:Commerce:Shipping:InPost";

    public const string EnvironmentProduction = "production";
    public const string EnvironmentSandbox = "sandbox";

    public const string CountryPl = "PL";
    public const string CountryUk = "GB";

    public bool Enabled { get; set; } = true;

    [Required]
    public string ApiToken { get; set; } = string.Empty;

    [Range(1, int.MaxValue)]
    public int OrganizationId { get; set; }

    [Required]
    public string Environment { get; set; } = EnvironmentProduction;

    [Required]
    public string Country { get; set; } = CountryPl;

    public string? BaseUrlOverride { get; set; }

    public int RequestTimeoutSeconds { get; set; } = 30;

    [Required]
    public string SenderFirstName { get; set; } = "Sender";

    [Required]
    public string SenderLastName { get; set; } = "Company";

    [Required]
    [EmailAddress]
    public string SenderEmail { get; set; } = "sender@example.com";

    [Required]
    public string SenderPhone { get; set; } = "500600700";

    [Required]
    public string SenderCompanyName { get; set; } = "Store";

    [Required]
    public string SenderStreet { get; set; } = "Main Street";

    [Required]
    public string SenderBuildingNumber { get; set; } = "1";

    [Required]
    public string SenderPostalCode { get; set; } = "00-000";

    [Required]
    public string SenderCity { get; set; } = "Warsaw";

    [Required]
    public string SenderCountryCode { get; set; } = CountryPl;

    [Range(0.001d, double.MaxValue)]
    public decimal DefaultParcelWeightKg { get; set; } = 1m;

    [Range(1d, double.MaxValue)]
    public decimal DefaultParcelLengthMm { get; set; } = 200m;

    [Range(1d, double.MaxValue)]
    public decimal DefaultParcelWidthMm { get; set; } = 200m;

    [Range(1d, double.MaxValue)]
    public decimal DefaultParcelHeightMm { get; set; } = 100m;

    public string? DefaultLockerPointId { get; set; }

    public decimal ParcelLockerPrice { get; set; } = 10.99m;

    public decimal CourierPrice { get; set; } = 14.99m;

    [Required]
    public string Currency { get; set; } = "PLN";

    public void CopyTo(InPostShippingOptions target)
    {
        ArgumentNullException.ThrowIfNull(target);

        target.Enabled = Enabled;
        target.ApiToken = (ApiToken ?? string.Empty).Trim();
        target.OrganizationId = OrganizationId;
        target.Environment = (Environment ?? EnvironmentProduction).Trim().ToLowerInvariant();
        target.Country = (Country ?? CountryPl).Trim().ToUpperInvariant();
        target.BaseUrlOverride = string.IsNullOrWhiteSpace(BaseUrlOverride) ? null : BaseUrlOverride.Trim();
        target.RequestTimeoutSeconds = RequestTimeoutSeconds;

        target.SenderFirstName = (SenderFirstName ?? string.Empty).Trim();
        target.SenderLastName = (SenderLastName ?? string.Empty).Trim();
        target.SenderEmail = (SenderEmail ?? string.Empty).Trim();
        target.SenderPhone = (SenderPhone ?? string.Empty).Trim();
        target.SenderCompanyName = (SenderCompanyName ?? string.Empty).Trim();
        target.SenderStreet = (SenderStreet ?? string.Empty).Trim();
        target.SenderBuildingNumber = (SenderBuildingNumber ?? string.Empty).Trim();
        target.SenderPostalCode = (SenderPostalCode ?? string.Empty).Trim();
        target.SenderCity = (SenderCity ?? string.Empty).Trim();
        target.SenderCountryCode = (SenderCountryCode ?? CountryPl).Trim().ToUpperInvariant();

        target.DefaultParcelWeightKg = DefaultParcelWeightKg;
        target.DefaultParcelLengthMm = DefaultParcelLengthMm;
        target.DefaultParcelWidthMm = DefaultParcelWidthMm;
        target.DefaultParcelHeightMm = DefaultParcelHeightMm;
        target.DefaultLockerPointId = string.IsNullOrWhiteSpace(DefaultLockerPointId) ? null : DefaultLockerPointId.Trim();

        target.ParcelLockerPrice = ParcelLockerPrice;
        target.CourierPrice = CourierPrice;
        target.Currency = (Currency ?? "PLN").Trim().ToUpperInvariant();
    }
}