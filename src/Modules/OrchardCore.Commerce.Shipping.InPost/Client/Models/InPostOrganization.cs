using System;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

/// <summary>
/// The ShipX organization resource returned by <c>GET /v1/organizations/{organizationId}</c>. The properties follow
/// the response (snake_case on the wire). The response's <c>href</c> link is not modeled.
/// </summary>
public sealed record InPostOrganization(
    long Id,
    long OwnerId,
    string Name,
    string? TaxId,
    string? BankAccountNumber,
    IReadOnlyList<string>? Carriers,
    IReadOnlyList<string>? Services,
    InPostAddress? Address,
    InPostAddress? InvoiceAddress,
    InPostContactPerson? ContactPerson,
    DateTimeOffset? CreatedAt,
    DateTimeOffset? UpdatedAt);


public sealed record InPostContactPerson(
    long Id,
    string? Email,
    string? Phone,
    string? FirstName,
    string? LastName);