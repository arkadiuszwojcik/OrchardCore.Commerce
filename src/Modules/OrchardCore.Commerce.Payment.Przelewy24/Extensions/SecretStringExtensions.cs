using Microsoft.AspNetCore.DataProtection;
using Microsoft.Extensions.Logging;
using OrchardCore.Commerce.Payment.Przelewy24.Services;
using System;

namespace OrchardCore.Commerce.Payment.Przelewy24.Extensions;

public static class SecretStringExtensions
{
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
            var protector = dataProtectionProvider.CreateProtector(nameof(Przelewy24SettingsConfiguration));
            return protector.Unprotect(encryptedKey);
        }
        catch (Exception exception)
        {
            logger.LogError(
                exception,
                "The Przelewy24 secret string could not be decrypted. It may have been encrypted using a different key.");
            return null;
        }
    }
}
