#nullable enable
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Entities;
using System;
using System.Collections.Generic;
using System.Text.Json.Nodes;
using System.Text.Json.Serialization;

namespace OrchardCore.Commerce.Shipping.Models;

/// <summary>
/// A shipping provider connection (settings for one carrier account). It is a plain entity, not a content item:
/// the fields every connection has live on the class, while each provider's own settings are stored in
/// <see cref="Entity.Properties"/> by that provider's display driver (via <c>Put&lt;T&gt;</c>). Secrets are never part of
/// the entity, they are stored through <see cref="Services.IShippingCredentialsService"/> under <see cref="Id"/>.
/// </summary>
public class ShippingProviderConnection : Entity
{
    public string Id { get; set; } = Guid.NewGuid().ToString("N");

    /// <summary>
    /// Gets or sets the key of the <see cref="IShippingProvider"/> this connection belongs to. It decides which
    /// provider module's editor is shown for the connection.
    /// </summary>
    public string ProviderKey { get; set; } = string.Empty;

    public string Name { get; set; } = string.Empty;
    public bool IsEnabled { get; set; } = true;
    public bool IsTestMode { get; set; }
    public long ConfigurationRevision { get; set; }
    public long CredentialVersion { get; set; }
    public DateTime CreatedUtc { get; set; }

    /// <summary>
    /// Gets the secrets a provider's editor wants to store. They are not persisted with the entity: the manager writes
    /// them to the secret store when the connection is saved, so nothing is stored when the editor form is invalid.
    /// </summary>
    [JsonIgnore]
    public IDictionary<string, string> PendingCredentials { get; } = new Dictionary<string, string>();

    /// <summary>
    /// Marks the connection configuration as changed, which invalidates cached provider results.
    /// </summary>
    public void MarkConfigurationChanged() => ConfigurationRevision++;

    /// <summary>
    /// Queues a secret to be stored the next time the connection is saved.
    /// </summary>
    public void SetCredential(string key, string value) => PendingCredentials[key] = value;

    public ShippingProviderConnection Clone() =>
        new()
        {
            Id = Id,
            ProviderKey = ProviderKey,
            Name = Name,
            IsEnabled = IsEnabled,
            IsTestMode = IsTestMode,
            ConfigurationRevision = ConfigurationRevision,
            CredentialVersion = CredentialVersion,
            CreatedUtc = CreatedUtc,
            Properties = (JsonObject)Properties.DeepClone(),
        };

    /// <summary>
    /// Builds the runtime context passed to the provider. The provider-specific settings go to
    /// <see cref="ShippingProviderConnectionContext.Metadata"/> keyed by their type name, so the provider can read
    /// them with <see cref="ShippingProviderConnectionContextExtensions.GetSettings{T}"/>.
    /// </summary>
    public ShippingProviderConnectionContext ToContext(IDictionary<string, string> credentials)
    {
        var metadata = new Dictionary<string, object>();
        foreach (var (key, value) in Properties)
        {
            if (value is not null) metadata[key] = value.DeepClone();
        }

        return new ShippingProviderConnectionContext(
            Id,
            credentials,
            Metadata: metadata,
            ProviderKey: ProviderKey,
            ConfigurationRevision: ConfigurationRevision,
            CredentialVersion: CredentialVersion,
            IsTestMode: IsTestMode);
    }
}
