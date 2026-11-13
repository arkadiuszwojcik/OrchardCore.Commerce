using Lombiq.HelpfulLibraries.OrchardCore.Users;
using OrchardCore.Security.Permissions;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Payment;

public class Permissions : AdminPermissionBase
{
    public static readonly Permission ManageOrders = new(nameof(ManageOrders), "Manage Orders");
    public static readonly Permission Checkout = new(nameof(Checkout), "Ability to checkout");
    public static readonly Permission ManageAddressDisplaySettings = new(nameof(ManageAddressDisplaySettings), "Manage Address Settings");
    public static readonly Permission UseSandboxCheckout = new(nameof(UseSandboxCheckout), "Use Sandbox Checkout Mode");

    private static readonly IReadOnlyList<Permission> _adminPermissions =
        [ManageOrders, Checkout, ManageAddressDisplaySettings, UseSandboxCheckout];

    protected override IEnumerable<Permission> AdminPermissions => _adminPermissions;
}
