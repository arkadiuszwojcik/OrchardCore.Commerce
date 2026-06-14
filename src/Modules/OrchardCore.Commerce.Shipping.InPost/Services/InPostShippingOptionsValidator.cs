using Microsoft.Extensions.Options;
using OrchardCore.Commerce.Shipping.InPost.Options;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.InPost.Services;

public class InPostShippingOptionsValidator : IValidateOptions<InPostShippingOptions>
{
    public ValidateOptionsResult Validate(string? name, InPostShippingOptions options)
    {
        var failures = new List<string>();

        if (!options.Enabled)
        {
            return ValidateOptionsResult.Success;
        }

        if (string.IsNullOrWhiteSpace(options.ApiToken))
        {
            failures.Add("InPost ShipX API token is required when InPost shipping is enabled.");
        }

        if (options.OrganizationId <= 0)
        {
            failures.Add("InPost organization id must be greater than zero.");
        }

        if (string.IsNullOrWhiteSpace(options.Currency))
        {
            failures.Add("Currency is required.");
        }

        if (string.IsNullOrWhiteSpace(options.SenderCountryCode) ||
            string.IsNullOrWhiteSpace(options.SenderStreet) ||
            string.IsNullOrWhiteSpace(options.SenderCity) ||
            string.IsNullOrWhiteSpace(options.SenderPostalCode))
        {
            failures.Add("Sender address must include country code, street, city, and postal code.");
        }

        if (options.DefaultParcelWeightKg <= 0 ||
            options.DefaultParcelLengthMm <= 0 ||
            options.DefaultParcelWidthMm <= 0 ||
            options.DefaultParcelHeightMm <= 0)
        {
            failures.Add("Default parcel weight and dimensions must be positive values.");
        }

        return failures.Count > 0
            ? ValidateOptionsResult.Fail(failures)
            : ValidateOptionsResult.Success;
    }
}