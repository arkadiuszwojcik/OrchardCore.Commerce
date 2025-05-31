using Microsoft.AspNetCore.DataProtection;
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Payment.Przelewy24.Constants;
using System.Security.Cryptography;

namespace OrchardCore.Commerce.Payment.Przelewy24.Extensions;

public static class SecretStringExtensions
{
    public static string EncryptSecretString(
        this string keyToEncrypt, IDataProtectionProvider dataProtectionProvider)
    {
        var protector = dataProtectionProvider.CreateProtector(Przelewy24Constants.Features.Przelewy24);
        return protector.Protect(keyToEncrypt);
    }

    public static string DecryptSecretString(
        this string encryptedKey, IDataProtectionProvider dataProtectionProvider, ILogger logger
)
    {
        if (string.IsNullOrWhiteSpace(encryptedKey))
        {
            return null;
        }

        try
        {
            var protector = dataProtectionProvider.CreateProtector(Przelewy24Constants.Features.Przelewy24);
            return protector.Unprotect(encryptedKey);
        }
        catch (CryptographicException exception)
        {
            logger.LogError(
                exception,
                "The Przelewy24 secret string could not be decrypted. It may have been encrypted using a different key.");
            return null;
        }
    }
}
