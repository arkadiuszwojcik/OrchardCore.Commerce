using Microsoft.AspNetCore.DataProtection;
using Microsoft.AspNetCore.Mvc.ModelBinding;
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Payment.Przelewy24.Extensions;
using System;
using System.ComponentModel.DataAnnotations;

namespace OrchardCore.Commerce.Payment.Przelewy24.Settings;

public class Przelewy24Settings
{
    public string BaseAddress { get; set; } = "https://sandbox.przelewy24.pl/";

    public int MerchantId { get; set; }

    public int? PosId { get; set; }

    public string CrcKey { get; set; }

    public string ApiKey { get; set; }

    /*
    public void CopyTo(Przelewy24Settings target)
    {
        if (Uri.TryCreate(BaseAddress, UriKind.Absolute, out var baseUri)) target.BaseAddress = baseUri.AbsoluteUri;
        if (!string.IsNullOrWhiteSpace(CrcKey)) target.CrcKey = CrcKey.Trim();
        if (!string.IsNullOrWhiteSpace(ApiKey)) target.ApiKey = ApiKey.Trim();
        target.MerchantId = MerchantId;
        target.PosId = PosId;
    }*/
}
