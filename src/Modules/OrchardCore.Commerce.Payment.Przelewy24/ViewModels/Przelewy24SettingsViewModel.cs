using System.ComponentModel.DataAnnotations;

namespace OrchardCore.Commerce.Payment.Przelewy24.ViewModels;

public class Przelewy24SettingsViewModel
{
    [Required]
    public int MerchantId { get; set; }

    public int? PosId { get; set; }

    [Required(AllowEmptyStrings = false, ErrorMessage = "Crc key is required")]
    public string CrcKey { get; set; }

    [Required(AllowEmptyStrings = false, ErrorMessage = "API key is required")]
    public string ApiKey { get; set; }

    public bool HasDecryptionError { get; set; }
}
