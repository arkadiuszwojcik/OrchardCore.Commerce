using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.Commerce.Shipping.Services.Secrets;
using OrchardCore.Commerce.Shipping.Tests.Fakes;
using System.Collections.Generic;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class ShippingCredentialsServiceTests
{
    private static ShippingCredentialsService Create() =>
        new(new SecretManager([new InMemorySecretStore()]));

    [Fact]
    public async Task SavedCredentialsAreReadBack()
    {
        var service = Create();

        await service.SaveCredentialsAsync("c1", new Dictionary<string, string> { ["ApiToken"] = "t" });

        Assert.True(await service.HasCredentialsAsync("c1"));
        Assert.Equal("t", (await service.GetCredentialsAsync("c1"))["ApiToken"]);
    }

    [Fact]
    public async Task MissingCredentialsAreEmpty()
    {
        var service = Create();

        Assert.False(await service.HasCredentialsAsync("nope"));
        Assert.Empty(await service.GetCredentialsAsync("nope"));
    }

    [Fact]
    public async Task CredentialsAreIsolatedPerConnectionAndRemovable()
    {
        var service = Create();
        await service.SaveCredentialsAsync("a", new Dictionary<string, string> { ["k"] = "1" });
        await service.SaveCredentialsAsync("b", new Dictionary<string, string> { ["k"] = "2" });

        await service.RemoveCredentialsAsync("a");

        Assert.False(await service.HasCredentialsAsync("a"));
        Assert.Equal("2", (await service.GetCredentialsAsync("b"))["k"]);
    }
}
