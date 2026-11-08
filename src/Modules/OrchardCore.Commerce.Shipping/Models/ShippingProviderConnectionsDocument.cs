using OrchardCore.Data.Documents;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.Models;

/// <summary>
/// The single document that holds all shipping provider connections of a tenant.
/// </summary>
public class ShippingProviderConnectionsDocument : Document
{
    public Dictionary<string, ShippingProviderConnection> Connections { get; set; } = [];
}
