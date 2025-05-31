using OrchardCore.Commerce.MoneyDataType;
using System;
using System.Collections.Generic;
using System.Linq;

namespace OrchardCore.Commerce.Payment.Przelewy24.Helpers;

public static class AmountHelpers
{
    // There are many more, but even JPY is not supported by Przelewy24, so this only highlights the existence of such cases.
    private static readonly IEnumerable<string> ZeroDecimalCurrencies =
    [
        "JPY",
    ];

    public static long GetPaymentAmount(Amount total)
    {
        var rounding = ZeroDecimalCurrencies.Select(code => (Code: code, KeepDigits: 0, RoundTens: 0))
            .ToDictionary(item => item.Code, item => (item.KeepDigits, item.RoundTens));

        return total.GetFixedPointAmount(rounding);
    }
}
