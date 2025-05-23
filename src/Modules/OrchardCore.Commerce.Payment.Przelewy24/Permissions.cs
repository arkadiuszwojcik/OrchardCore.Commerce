using Lombiq.HelpfulLibraries.OrchardCore.Users;
using OrchardCore.Security.Permissions;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Payment.Przelewy24;

public class Permissions : AdminPermissionBase
{
    public static readonly Permission ManagePrzelewy24Settings =
        new(nameof(ManagePrzelewy24Settings), "Manage Przelewy24 settings.");

    protected override IEnumerable<Permission> AdminPermissions => [ManagePrzelewy24Settings];
}
