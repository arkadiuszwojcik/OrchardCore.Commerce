using Lombiq.HelpfulLibraries.OrchardCore.Users;
using OrchardCore.Security.Permissions;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Permissions;

public class ShippingPermissions : AdminPermissionBase
{
    public static readonly Permission ManageShippingSettings =
        new(nameof(ManageShippingSettings), "Manage Shipping Settings");

    public static readonly Permission ManageShippingProviderConnections =
        new(nameof(ManageShippingProviderConnections), "Manage Shipping Provider Connections");

    public static readonly Permission ManageShippingOrigins =
        new(nameof(ManageShippingOrigins), "Manage Shipping Origins");

    public static readonly Permission ManageShippingMethods =
        new(nameof(ManageShippingMethods), "Manage Shipping Methods");

    public static readonly Permission ManageShipments =
        new(nameof(ManageShipments), "Manage Shipments");

    protected override IEnumerable<Permission> AdminPermissions =>
    [
        ManageShippingSettings,
        ManageShippingProviderConnections,
        ManageShippingOrigins,
        ManageShippingMethods,
        ManageShipments,
    ];
}
