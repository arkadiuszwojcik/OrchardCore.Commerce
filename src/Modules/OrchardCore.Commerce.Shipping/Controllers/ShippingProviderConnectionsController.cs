#nullable enable
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.Localization;
using OrchardCore.Admin;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Permissions;
using OrchardCore.Commerce.Shipping.Services;
using OrchardCore.Commerce.Shipping.ViewModels;
using OrchardCore.DisplayManagement;
using OrchardCore.DisplayManagement.ModelBinding;
using OrchardCore.DisplayManagement.Notify;
using System;
using System.Linq;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Controllers;

/// <summary>
/// Lists, creates, edits and deletes shipping provider connections. The editor of a connection is composed by display
/// drivers: the core one for the shared fields and the one each provider module registers for its own fields.
/// </summary>
[Admin]
public class ShippingProviderConnectionsController : Controller
{
    private readonly IAuthorizationService _authorizationService;
    private readonly IShippingProviderConnectionManager _connectionManager;
    private readonly IShippingProviderRegistry _providerRegistry;
    private readonly IDisplayManager<ShippingProviderConnection> _displayManager;
    private readonly IUpdateModelAccessor _updateModelAccessor;
    private readonly INotifier _notifier;
    private readonly IHtmlLocalizer<ShippingProviderConnectionsController> H;

    public ShippingProviderConnectionsController(
        IAuthorizationService authorizationService,
        IShippingProviderConnectionManager connectionManager,
        IShippingProviderRegistry providerRegistry,
        IDisplayManager<ShippingProviderConnection> displayManager,
        IUpdateModelAccessor updateModelAccessor,
        INotifier notifier,
        IHtmlLocalizer<ShippingProviderConnectionsController> htmlLocalizer)
    {
        _authorizationService = authorizationService;
        _connectionManager = connectionManager;
        _providerRegistry = providerRegistry;
        _displayManager = displayManager;
        _updateModelAccessor = updateModelAccessor;
        _notifier = notifier;
        H = htmlLocalizer;
    }

    public async Task<IActionResult> Index(string? search)
    {
        if (!await IsAuthorizedAsync()) return Forbid();

        var connections = await _connectionManager.GetAllAsync();
        if (!string.IsNullOrWhiteSpace(search))
        {
            connections = connections
                .Where(connection => connection.Name.Contains(search, StringComparison.OrdinalIgnoreCase))
                .ToList();
        }

        return View(new ShippingProviderConnectionListViewModel
        {
            Search = search ?? string.Empty,
            Connections = connections
                .Select(connection => new ShippingProviderConnectionListItem
                {
                    Id = connection.Id,
                    Name = connection.Name,
                    ProviderKey = connection.ProviderKey,
                    ProviderDisplayName = GetDisplayName(connection.ProviderKey),
                    IsEnabled = connection.IsEnabled,
                    IsTestMode = connection.IsTestMode,
                })
                .ToList(),
            Providers = _providerRegistry.GetAllProviders()
                .Select(provider =>
                {
                    var descriptor = provider.GetDescriptor();
                    return new ShippingProviderOption
                    {
                        ProviderKey = provider.ProviderKey,
                        DisplayName = descriptor.DisplayName,
                        Description = descriptor.Description ?? string.Empty,
                    };
                })
                .ToList(),
        });
    }

    public async Task<IActionResult> Create(string id)
    {
        if (!await IsAuthorizedAsync()) return Forbid();

        var connection = _connectionManager.New(id);
        if (connection is null)
        {
            await _notifier.ErrorAsync(H["Unable to find a shipping provider named '{0}'.", id]);
            return RedirectToAction(nameof(Index));
        }

        return View("Edit", new ShippingProviderConnectionEditorViewModel
        {
            DisplayName = GetDisplayName(connection.ProviderKey),
            IsNew = true,
            Editor = await _displayManager.BuildEditorAsync(connection, _updateModelAccessor.ModelUpdater, isNew: true),
        });
    }

    [HttpPost]
    [ActionName(nameof(Create))]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> CreatePost(string id)
    {
        if (!await IsAuthorizedAsync()) return Forbid();

        var connection = _connectionManager.New(id);
        if (connection is null)
        {
            await _notifier.ErrorAsync(H["Unable to find a shipping provider named '{0}'.", id]);
            return RedirectToAction(nameof(Index));
        }

        var editor = await _displayManager.UpdateEditorAsync(connection, _updateModelAccessor.ModelUpdater, isNew: true);
        if (ModelState.IsValid)
        {
            await _connectionManager.SaveAsync(connection);
            await _notifier.SuccessAsync(H["The connection has been created."]);

            return RedirectToAction(nameof(Edit), new { id = connection.Id });
        }

        return View("Edit", new ShippingProviderConnectionEditorViewModel
        {
            DisplayName = GetDisplayName(connection.ProviderKey),
            IsNew = true,
            Editor = editor,
        });
    }

    public async Task<IActionResult> Edit(string id)
    {
        if (!await IsAuthorizedAsync()) return Forbid();

        var connection = await _connectionManager.FindByIdAsync(id);
        if (connection is null) return NotFound();

        return View(new ShippingProviderConnectionEditorViewModel
        {
            DisplayName = connection.Name,
            Editor = await _displayManager.BuildEditorAsync(connection, _updateModelAccessor.ModelUpdater, isNew: false),
        });
    }

    [HttpPost]
    [ActionName(nameof(Edit))]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> EditPost(string id)
    {
        if (!await IsAuthorizedAsync()) return Forbid();

        var connection = await _connectionManager.FindByIdAsync(id);
        if (connection is null) return NotFound();

        var editor = await _displayManager.UpdateEditorAsync(connection, _updateModelAccessor.ModelUpdater, isNew: false);
        if (ModelState.IsValid)
        {
            await _connectionManager.SaveAsync(connection);
            await _notifier.SuccessAsync(H["The connection has been saved."]);

            return RedirectToAction(nameof(Edit), new { id });
        }

        return View(new ShippingProviderConnectionEditorViewModel
        {
            DisplayName = connection.Name,
            Editor = editor,
        });
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> Delete(string id)
    {
        if (!await IsAuthorizedAsync()) return Forbid();

        if (!await _connectionManager.DeleteAsync(id)) return NotFound();

        await _notifier.SuccessAsync(H["The connection has been deleted."]);
        return RedirectToAction(nameof(Index));
    }

    private async Task<bool> IsAuthorizedAsync() =>
        await _authorizationService.AuthorizeAsync(User, ShippingPermissions.ManageShippingProviderConnections);

    private string GetDisplayName(string providerKey) =>
        _providerRegistry.GetProvider(providerKey)?.GetDescriptor().DisplayName ?? providerKey;
}
