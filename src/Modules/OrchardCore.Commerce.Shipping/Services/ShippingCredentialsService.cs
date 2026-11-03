#nullable enable
using OrchardCore.Secrets;
using System;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Reads and writes the credential set of a shipping provider connection through <see cref="ISecretManager"/>.
/// The whole set is stored as one <see cref="TextSecret"/> (a JSON object) so credentials never live in content items.
/// </summary>
public interface IShippingCredentialsService
{
    Task<IDictionary<string, string>> GetCredentialsAsync(string connectionContentItemId);

    Task<bool> HasCredentialsAsync(string connectionContentItemId);

    Task SaveCredentialsAsync(string connectionContentItemId, IDictionary<string, string> credentials);

    Task RemoveCredentialsAsync(string connectionContentItemId);
}

public class ShippingCredentialsService : IShippingCredentialsService
{
    private readonly ISecretManager _secretManager;

    public ShippingCredentialsService(ISecretManager secretManager) => _secretManager = secretManager;

    public async Task<IDictionary<string, string>> GetCredentialsAsync(string connectionContentItemId)
    {
        var secret = await _secretManager.GetSecretAsync<TextSecret>(GetName(connectionContentItemId));
        if (string.IsNullOrWhiteSpace(secret?.Text)) return new Dictionary<string, string>();

        try
        {
            return JsonSerializer.Deserialize<Dictionary<string, string>>(secret.Text)
                ?? new Dictionary<string, string>();
        }
        catch (JsonException)
        {
            return new Dictionary<string, string>();
        }
    }

    public async Task<bool> HasCredentialsAsync(string connectionContentItemId) =>
        await _secretManager.GetSecretAsync<TextSecret>(GetName(connectionContentItemId)) != null;

    public Task SaveCredentialsAsync(string connectionContentItemId, IDictionary<string, string> credentials) =>
        _secretManager.SaveSecretAsync(
            GetName(connectionContentItemId),
            new TextSecret { Text = JsonSerializer.Serialize(credentials) },
            new SecretSaveOptions { Description = "Shipping provider connection credentials." });

    public Task RemoveCredentialsAsync(string connectionContentItemId) =>
        _secretManager.RemoveSecretAsync(GetName(connectionContentItemId));

    private static string GetName(string connectionContentItemId) =>
        string.IsNullOrWhiteSpace(connectionContentItemId)
            ? throw new ArgumentException("Connection ID is required.", nameof(connectionContentItemId))
            : $"Shipping.Connection.{connectionContentItemId}";
}
