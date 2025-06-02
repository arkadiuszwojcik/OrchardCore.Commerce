namespace OrchardCore.Commerce.Payment.Przelewy24.Settings;

public class Przelewy24Settings
{
    public string BaseAddress { get; set; } = "https://sandbox.przelewy24.pl/";

    public int MerchantId { get; set; }

    public int? PosId { get; set; }

    public string CrcKey { get; set; }

    public string ApiKey { get; set; }
}
