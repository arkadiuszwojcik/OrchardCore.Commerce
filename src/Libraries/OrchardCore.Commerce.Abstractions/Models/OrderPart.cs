using OrchardCore.Commerce.Abstractions.Constants;
using OrchardCore.Commerce.Abstractions.Fields;
using OrchardCore.Commerce.MoneyDataType;
using OrchardCore.Commerce.MoneyDataType.Extensions;
using OrchardCore.ContentFields.Fields;
using OrchardCore.ContentManagement;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Text.Json.Nodes;
using System.Text.Json.Serialization;

namespace OrchardCore.Commerce.Abstractions.Models;

public class OrderPart : ContentPart
{
    public TextField OrderId { get; set; } = new();
    public TextField Status { get; set; } = new();

    /// <summary>
    /// Gets the order's line items.
    /// </summary>
    public IList<OrderLineItem> LineItems { get; } = [];

    /// <summary>
    /// Gets additional costs that don't belong to an <see cref="OrderLineItem"/>, such as taxes and shipping.
    /// </summary>
    public IList<OrderAdditionalCost> AdditionalCosts { get; } = [];

    /// <summary>
    /// Gets the amounts charged for this order. Typically, a single credit card charge.
    /// </summary>
    public IList<Payment> Charges { get; } = [];

    public TextField Email { get; set; } = new();
    public TextField Phone { get; set; } = new();
    public TextField VatNumber { get; set; } = new();

    public AddressField BillingAddress { get; set; } = new();
    public AddressField ShippingAddress { get; set; } = new();
    public BooleanField BillingAndShippingAddressesMatch { get; set; } = new();
    public BooleanField IsCorporation { get; set; } = new();

    /// <summary>
    /// Gets or sets whether this order's payment and shipping providers should be routed to their sandbox/test environments.
    /// </summary>
    public BooleanField IsTestOrder { get; set; } = new();

    public IDictionary<string, JsonNode> AdditionalData { get; } = new Dictionary<string, JsonNode>();

    [JsonIgnore]
    public bool IsPending => string.IsNullOrWhiteSpace(Status?.Text) || Status.Text.EqualsOrdinalIgnoreCase(OrderStatusCodes.Pending);

    [JsonIgnore]
    public bool IsOrdered => Status?.Text?.EqualsOrdinalIgnoreCase(OrderStatusCodes.Ordered) == true;

    [JsonIgnore]
    public bool IsFailed => Status?.Text?.EqualsOrdinalIgnoreCase(OrderStatusCodes.PaymentFailed) == true;

    /// <summary>
    /// Gets the total amount expected to be paid for this order: the sum of all <see cref="LineItems"/> plus any <see
    /// cref="AdditionalCosts"/> (e.g. taxes, shipping), since the total isn't always a simple sum of line prices.
    /// </summary>
    public Amount GetExpectedTotal() =>
        LineItems.Select(item => item.LinePrice).Concat(AdditionalCosts.Select(cost => cost.Cost)).Sum();

    /// <summary>
    /// Sets the <see cref="Status"/> to <see cref="OrderStatusCodes.PaymentFailed"/>.
    /// </summary>
    public void FailPayment() => Status.Text = OrderStatusCodes.PaymentFailed;

    /// <summary>
    /// Sets the <see cref="Status"/> to <see cref="OrderStatusCodes.Ordered"/>.
    /// </summary>
    public void SucceedPayment() => Status.Text = OrderStatusCodes.Ordered;
}
