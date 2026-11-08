using Microsoft.Extensions.Logging.Abstractions;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.Commerce.Shipping.Services.Secrets;
using OrchardCore.Commerce.Shipping.Tests.Fakes;
using OrchardCore.Entities;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class ShippingProviderConnectionTests
{
    // A provider module's own settings class, stored in the connection's properties under its type name.
    private sealed class FakeConnectionSettings
    {
        public string? Value { get; set; }
    }

    private sealed class Fixture
    {
        public Fixture(params IShippingProvider[] providers)
        {
            Credentials = new ShippingCredentialsService(new SecretManager([new InMemorySecretStore()]));
            Store = new ShippingProviderConnectionStore(new InMemoryDocumentManager<ShippingProviderConnectionsDocument>());
            Registry = new ShippingProviderRegistry(providers, NullLogger<ShippingProviderRegistry>.Instance);
            Manager = new ShippingProviderConnectionManager(Store, Registry, Credentials, new FakeTimeProvider());
        }

        public ShippingCredentialsService Credentials { get; }
        public ShippingProviderConnectionStore Store { get; }
        public ShippingProviderRegistry Registry { get; }
        public ShippingProviderConnectionManager Manager { get; }
    }

    private static Fixture CreateFixture() => new(new FakeShippingProvider { ProviderKey = "Fake" });

    [Fact]
    public void NewReturnsNothingForAnUnregisteredProvider()
    {
        Assert.Null(CreateFixture().Manager.New("Nope"));
    }

    [Fact]
    public void NewCreatesAnEnabledConnectionWithAUniqueIdForTheProvider()
    {
        var manager = CreateFixture().Manager;

        var first = manager.New("fake")!;
        var second = manager.New("Fake")!;

        Assert.Equal("Fake", first.ProviderKey);
        Assert.True(first.IsEnabled);
        Assert.NotEqual(first.Id, second.Id);
        Assert.Equal(new DateTime(2026, 1, 1, 12, 0, 0, DateTimeKind.Utc), first.CreatedUtc);
    }

    [Fact]
    public async Task ProviderSettingsAreStoredInPropertiesAndReadBack()
    {
        var manager = CreateFixture().Manager;
        var connection = manager.New("Fake")!;
        connection.Name = "Main";
        connection.Put(new FakeConnectionSettings { Value = "42" });

        await manager.SaveAsync(connection);

        var loaded = (await manager.FindByIdAsync(connection.Id))!;
        Assert.Equal("Main", loaded.Name);
        Assert.Equal("42", loaded.GetOrCreate<FakeConnectionSettings>().Value);
        Assert.True(loaded.Properties.ContainsKey(nameof(FakeConnectionSettings)));
    }

    [Fact]
    public async Task LoadedConnectionsAreCopiesThatDoNotChangeTheStoredOnes()
    {
        var manager = CreateFixture().Manager;
        var connection = manager.New("Fake")!;
        connection.Name = "Main";
        connection.Put(new FakeConnectionSettings { Value = "1" });
        await manager.SaveAsync(connection);

        var loaded = (await manager.FindByIdAsync(connection.Id))!;
        loaded.Name = "Changed";
        loaded.Put(new FakeConnectionSettings { Value = "2" });

        var again = (await manager.FindByIdAsync(connection.Id))!;
        Assert.Equal("Main", again.Name);
        Assert.Equal("1", again.GetOrCreate<FakeConnectionSettings>().Value);
    }

    [Fact]
    public async Task ConnectionsAreListedByName()
    {
        var manager = CreateFixture().Manager;
        foreach (var name in new[] { "b", "C", "a" })
        {
            var connection = manager.New("Fake")!;
            connection.Name = name;
            await manager.SaveAsync(connection);
        }

        Assert.Equal(["a", "b", "C"], (await manager.GetAllAsync()).Select(connection => connection.Name));
    }

    [Fact]
    public async Task SavingPendingCredentialsStoresThemAndBumpsTheVersions()
    {
        var fixture = CreateFixture();
        var connection = fixture.Manager.New("Fake")!;
        connection.SetCredential("ApiKey", "k");

        await fixture.Manager.SaveAsync(connection);

        Assert.Equal("k", (await fixture.Credentials.GetCredentialsAsync(connection.Id))["ApiKey"]);
        Assert.Equal(1, connection.CredentialVersion);
        Assert.Equal(1, connection.ConfigurationRevision);
        Assert.Empty(connection.PendingCredentials);
        Assert.Equal(1, (await fixture.Manager.FindByIdAsync(connection.Id))!.CredentialVersion);
    }

    [Fact]
    public async Task SavingWithoutPendingCredentialsKeepsTheStoredOnesAndVersions()
    {
        var fixture = CreateFixture();
        var connection = fixture.Manager.New("Fake")!;
        connection.SetCredential("ApiKey", "k");
        await fixture.Manager.SaveAsync(connection);

        var loaded = (await fixture.Manager.FindByIdAsync(connection.Id))!;
        loaded.Name = "Renamed";
        await fixture.Manager.SaveAsync(loaded);

        Assert.Equal("k", (await fixture.Credentials.GetCredentialsAsync(connection.Id))["ApiKey"]);
        Assert.Equal(1, (await fixture.Manager.FindByIdAsync(connection.Id))!.CredentialVersion);
    }

    [Fact]
    public async Task PendingCredentialsAreMergedWithTheStoredOnes()
    {
        var fixture = CreateFixture();
        var connection = fixture.Manager.New("Fake")!;
        connection.SetCredential("ApiKey", "k");
        await fixture.Manager.SaveAsync(connection);

        var loaded = (await fixture.Manager.FindByIdAsync(connection.Id))!;
        loaded.SetCredential("ApiSecret", "s");
        await fixture.Manager.SaveAsync(loaded);

        var credentials = await fixture.Credentials.GetCredentialsAsync(connection.Id);
        Assert.Equal("k", credentials["ApiKey"]);
        Assert.Equal("s", credentials["ApiSecret"]);
    }

    [Fact]
    public async Task PendingCredentialsAreNotPartOfTheStoredEntity()
    {
        var fixture = CreateFixture();
        var connection = fixture.Manager.New("Fake")!;

        // A form that failed validation never calls SaveAsync, so nothing may have reached the secret store.
        connection.SetCredential("ApiKey", "k");

        Assert.False(await fixture.Credentials.HasCredentialsAsync(connection.Id));
        Assert.Empty(connection.Clone().PendingCredentials);
    }

    [Fact]
    public async Task DeletingAConnectionRemovesItsCredentials()
    {
        var fixture = CreateFixture();
        var connection = fixture.Manager.New("Fake")!;
        connection.SetCredential("ApiKey", "k");
        await fixture.Manager.SaveAsync(connection);

        Assert.True(await fixture.Manager.DeleteAsync(connection.Id));

        Assert.Null(await fixture.Manager.FindByIdAsync(connection.Id));
        Assert.False(await fixture.Credentials.HasCredentialsAsync(connection.Id));
        Assert.False(await fixture.Manager.DeleteAsync(connection.Id));
    }

    [Fact]
    public async Task TheProviderContextCarriesCredentialsFlagsAndProviderSettings()
    {
        var fixture = CreateFixture();
        var connection = fixture.Manager.New("Fake")!;
        connection.IsTestMode = true;
        connection.Put(new FakeConnectionSettings { Value = "42" });
        connection.SetCredential("ApiKey", "k");
        await fixture.Manager.SaveAsync(connection);

        var context = await fixture.Manager.CreateContextAsync((await fixture.Manager.FindByIdAsync(connection.Id))!);

        Assert.Equal(connection.Id, context.ConnectionId);
        Assert.Equal("Fake", context.ProviderKey);
        Assert.True(context.IsTestMode);
        Assert.Equal(connection.ConfigurationRevision, context.ConfigurationRevision);
        Assert.Equal(1, context.CredentialVersion);
        Assert.Equal("k", context.Credentials["ApiKey"]);
        Assert.Equal("42", context.GetSettings<FakeConnectionSettings>()?.Value);
    }

    [Fact]
    public void MissingProviderSettingsReadAsNothing()
    {
        var context = new ShippingProviderConnectionContext("c", new Dictionary<string, string>());

        Assert.Null(context.GetSettings<FakeConnectionSettings>());
    }
}
