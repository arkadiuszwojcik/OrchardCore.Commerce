using System.Collections.Generic;
using System.Text.Json.Serialization;

namespace OrchardCore.Commerce.Payment.Przelewy24.Models;

public class TransactionRegisterRequest
{
    public int MerchantId { get; set; }
    public int PosId { get; set; }
    public string SessionId { get; set; }
    public int Amount { get; set; }
    public string Currency {  get; set; }
    public string Description { get; set; }
    public string Email { get; set; }
    public string? Client { get; set; }
    public string? Address { get; set; }
    public string? Zip { get; set; }
    public string? City { get; set; }
    public string Country { get; set; }
    public string? Phone { get; set; }
    public string Language { get; set; }
    public int? Method { get; set; }
    public string UrlReturn { get; set; }
    public string? UrlStatus { get; set; }
    public int? TimeLimit { get; set; }
    public int? Channel { get; set; }
    public bool? WaitForResult { get; set; }
    public bool? RegulationAccept { get; set; }
    public int? Shipping { get; set; }
    public string? TransferLabel { get; set; }
    public int? MobileLib { get; set; }
    public string? SdkVersion { get; set; }
    public string Sign { get; set; }
    public string? Encoding { get; set; }
    public string? MethodRefId { get; set; }
    public IList<CartParameters>? Cart { get; set; }
    public Additional? Additional { get; set; }
}

public class CartParameters
{
    public string SellerId { get; set; }
    public string SellerCategory { get; set; }
    public string? Name { get; set; }
    public string? Description { get; set; }
    public int? Quantity { get; set; }
    public int? Price { get; set; }
    public string? Number { get; set; }
}

public class Additional
{
    public Shipping? Shipping { get; set; }
    [JsonPropertyName("PSU")]
    public PSU? PSU { get; set; }
}

public class Shipping
{
    public int Type { get; set; }
    public string Address { get; set; }
    public string Zip { get; set; }
    public string City { get; set; }
    public string Country { get; set; }
}

public class PSU
{
    [JsonPropertyName("IP")]
    public string IP { get; set; }
    public string UserAgent { get; set; }
}
