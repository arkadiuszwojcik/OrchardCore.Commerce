using OrchardCore.Commerce.Payment.Przelewy24.Helpers;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Payment.Przelewy24.Models;

internal class TransactionNotification
{
    public int MerchantId { get; set; }
    public int PosId { get; set; }
    public string SessionId { get; set; }
    public int Amount { get; set; }
    public int OriginAmount { get; set; }
    public string Currency { get; set; }
    public long OrderId { get; set; }
    public int MethodId { get; set; }
    public string Statement { get; set; }
    public string Sign { get; set; }

    public bool VerifySign(string crc)
    {
        return string.Equals(Sign, CalculateSign(crc));
    }

    public string CalculateSign(string crc)
    {
        var data = new List<KeyValuePair<string, object>>
        {
            new("merchantId", MerchantId),
            new("posId", PosId),
            new("sessionId", SessionId),
            new("amount", Amount),
            new("originAmount", OriginAmount),
            new("currency", Currency),
            new("orderId", OrderId),
            new("methodId", MethodId),
            new("statement", Statement),
            new("crc", crc),
        };

        return Przelewy24Crypto.CalculateSign(data, crc);
    }
}
