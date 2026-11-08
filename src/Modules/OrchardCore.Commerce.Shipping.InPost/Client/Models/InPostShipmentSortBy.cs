namespace OrchardCore.Commerce.Shipping.InPost.Client.Models;

public enum InPostShipmentSortBy
{
    Id,
    CreatedAt,
    TrackingNumber,
    Service,
    Status,
    InsuranceAmount,
    CodAmount,
    ExternalCustomerId,
}

public enum InPostSortOrder
{
    Ascending,
    Descending,
}
