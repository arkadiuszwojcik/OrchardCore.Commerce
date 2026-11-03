using Microsoft.Extensions.Logging.Abstractions;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.Commerce.Shipping.Tests.Fakes;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class ShippingProviderRegistryTests
{
    [Fact]
    public void LookupIsCaseInsensitive()
    {
        var provider = new FakeShippingProvider { ProviderKey = "InPost" };
        var registry = new ShippingProviderRegistry([provider], NullLogger<ShippingProviderRegistry>.Instance);

        Assert.Same(provider, registry.GetProvider("inpost"));
    }

    [Fact]
    public void DuplicatesAreIgnoredAndFirstWins()
    {
        var first = new FakeShippingProvider { ProviderKey = "A" };
        var second = new FakeShippingProvider { ProviderKey = "a" };
        var registry = new ShippingProviderRegistry([first, second], NullLogger<ShippingProviderRegistry>.Instance);

        Assert.Same(first, registry.GetProvider("A"));
        Assert.Single(registry.GetAllProviders());
    }

    [Fact]
    public void UnknownOrEmptyProviderReturnsNull()
    {
        var registry = new ShippingProviderRegistry([], NullLogger<ShippingProviderRegistry>.Instance);

        Assert.Null(registry.GetProvider("x"));
        Assert.Null(registry.GetProvider(string.Empty));
    }
}
