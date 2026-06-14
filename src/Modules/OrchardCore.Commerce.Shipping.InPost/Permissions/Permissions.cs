using Lombiq.HelpfulLibraries.OrchardCore.Users;
using OrchardCore.Security.Permissions;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.InPost.Permissions;

public class InPostShippingPermissions : AdminPermissionBase
{
    public static readonly Permission ManageInPostShippingSettings =
        new(nameof(ManageInPostShippingSettings), "Manage InPost Shipping Settings");

    protected override IEnumerable<Permission> AdminPermissions { get; } =
    [
        ManageInPostShippingSettings,
    ];
}