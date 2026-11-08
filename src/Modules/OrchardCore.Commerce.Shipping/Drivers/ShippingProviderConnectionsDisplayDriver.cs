using Microsoft.Extensions.Localization;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.DisplayManagement.Handlers;
using OrchardCore.DisplayManagement.Views;
using OrchardCore.Mvc.ModelBinding;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Drivers;

/// <summary>
/// Edits the fields shared by every shipping provider connection. Each provider module adds its own
/// <c>DisplayDriver&lt;ShippingProviderConnection&gt;</c> next to this one for the fields that are specific to it.
/// </summary>
public sealed class ShippingProviderConnectionsDisplayDriver : DisplayDriver<ShippingProviderConnection>
{
    private readonly IShippingProviderRegistry _providerRegistry;
    private readonly IStringLocalizer S;

    public ShippingProviderConnectionsDisplayDriver(
        IShippingProviderRegistry providerRegistry,
        IStringLocalizer<ShippingProviderConnectionsDisplayDriver> stringLocalizer)
    {
        _providerRegistry = providerRegistry;
        S = stringLocalizer;
    }

    public override IDisplayResult Edit(ShippingProviderConnection connection, BuildEditorContext context) =>
        Initialize<ShippingProviderConnectionViewModel>("ShippingProviderConnection_Fields_Edit", model =>
        {
            model.Name = connection.Name;
            model.IsEnabled = connection.IsEnabled;
            model.IsTestMode = connection.IsTestMode;
            model.ProviderDisplayName = _providerRegistry.GetProvider(connection.ProviderKey)?.GetDescriptor().DisplayName
                ?? connection.ProviderKey;
        }).Location("Content:1");

    public override async Task<IDisplayResult> UpdateAsync(ShippingProviderConnection connection, UpdateEditorContext context)
    {
        var model = new ShippingProviderConnectionViewModel();

        await context.Updater.TryUpdateModelAsync(model, Prefix, m => m.Name, m => m.IsEnabled, m => m.IsTestMode);

        if (string.IsNullOrWhiteSpace(model.Name))
        {
            context.Updater.ModelState.AddModelError(Prefix, nameof(model.Name), S["The connection name is required."]);
        }

        if (connection.IsTestMode != model.IsTestMode) connection.MarkConfigurationChanged();

        connection.Name = model.Name?.Trim() ?? string.Empty;
        connection.IsEnabled = model.IsEnabled;
        connection.IsTestMode = model.IsTestMode;

        return Edit(connection, context);
    }
}
