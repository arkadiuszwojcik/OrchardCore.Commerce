using Microsoft.Extensions.DependencyInjection;
using OrchardCore.Commerce.Shipping.Abstractions;
using System.Text;
using System.Threading.Tasks;
using Xunit;

namespace OrchardCore.Commerce.Shipping.Tests;

public class ShippingStartupRegistrationTests
{
    [Fact]
    public async Task DocumentStoreIsRegisteredAndKeepsDocuments()
    {
        var services = new ServiceCollection();
        new Startup().ConfigureServices(services);
        services.AddDistributedMemoryCache();
        await using var provider = services.BuildServiceProvider();
        using var scope = provider.CreateScope();

        var store = scope.ServiceProvider.GetRequiredService<IShippingDocumentStore>();
        var cancellationToken = TestContext.Current.CancellationToken;

        var id = await store.StoreDocumentAsync("s1", "label", Encoding.UTF8.GetBytes("pdf"), "label.pdf", cancellationToken);

        Assert.Equal("pdf", Encoding.UTF8.GetString((await store.GetDocumentAsync(id, cancellationToken))!));

        await store.DeleteDocumentAsync(id, cancellationToken);
        Assert.Null(await store.GetDocumentAsync(id, cancellationToken));
    }
}
