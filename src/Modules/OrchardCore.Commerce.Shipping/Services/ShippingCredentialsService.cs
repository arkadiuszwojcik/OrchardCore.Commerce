#nullable enable
using OrchardCore.Secrets;
using System;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Services;

/// <summary>
/// Reads and writes the credential set of a shipping provider connection through <see cref="ISecretManager"/>.
/// The whole set is stored as one <see cref="TextSecret"/> (a JSON object) so credentials never live in the
/// connection document itself.
/// </summary>
public interface IShippingCredentialsService
{
    Task<IDictionary<string, string>> GetCredentialsAsync(string connectionId);

    Task<bool> HasCredentialsAsync(string connectionId);

    Task SaveCredentialsAsync(string connectionId, IDictionary<string, string> credentials);

    Task RemoveCredentialsAsync(string connectionId);
}

public class ShippingCredentialsService : IShippingCredentialsService
{
    private readonly ISecretManager _secretManager;

    public ShippingCredentialsService(ISecretManager secretManager) => _secretManager = secretManager;

    public async Task<IDictionary<string, string>> GetCredentialsAsync(string connectionId)
    {
        var secret = await _secretManager.GetSecretAsync<TextSecret>(GetName(connectionId));
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

    public async Task<bool> HasCredentialsAsync(string connectionId) =>
        await _secretManager.GetSecretAsync<TextSecret>(GetName(connectionId)) != null;

    public Task SaveCredentialsAsync(string connectionId, IDictionary<string, string> credentials) =>
        _secretManager.SaveSecretAsync(
            GetName(connectionId),
            new TextSecret { Text = JsonSerializer.Serialize(credentials) },
            new SecretSaveOptions { Description = "Shipping provider connection credentials." });

    public Task RemoveCredentialsAsync(string connectionId) =>
        _secretManager.RemoveSecretAsync(GetName(connectionId));

    private static string GetName(string connectionId) =>
        string.IsNullOrWhiteSpace(connectionId)
            ? throw new ArgumentException("Connection ID is required.", nameof(connectionId))
            : $"Shipping.Connection.{connectionId}";
}
