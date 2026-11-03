#nullable enable
using Lombiq.HelpfulLibraries.AspNetCore.Extensions;
using Lombiq.HelpfulLibraries.OrchardCore.Mvc;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Routing;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.Models;
using OrchardCore.Commerce.Shipping.Permissions;
using OrchardCore.ContentManagement;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.Endpoints.Api;

public static class ShipmentsEndpoint
{
    public static IEndpointRouteBuilder AddShipmentsEndpoints(this IEndpointRouteBuilder builder)
    {
        builder.MapGetWithDefaultSettings(
            "api/commerce/orders/{orderContentItemId}/shipments", GetShipmentsForOrderAsync);

        builder.MapPostWithDefaultSettings(
            "api/commerce/orders/{orderContentItemId}/shipments", CreateShipmentAsync);

        builder.MapGetWithDefaultSettings(
            "api/commerce/shipments/{shipmentId}", GetShipmentAsync);

        builder.MapPostWithDefaultSettings(
            "api/commerce/shipments/{shipmentId}/purchase", PurchaseShipmentAsync);

        builder.MapPostWithDefaultSettings(
            "api/commerce/shipments/{shipmentId}/cancel", CancelShipmentAsync);

        builder.MapPostWithDefaultSettings(
            "api/commerce/shipments/{shipmentId}/tracking/refresh", RefreshTrackingAsync);

        builder.MapGetWithDefaultSettings(
            "api/commerce/shipments/{shipmentId}/labels/{labelId}", GetLabelAsync);

        return builder;
    }

    private static async Task<IResult> GetShipmentsForOrderAsync(
        [FromRoute] string orderContentItemId,
        [FromServices] IAuthorizationService authorizationService,
        [FromServices] IContentManager contentManager,
        HttpContext httpContext)
    {
        if (!await authorizationService.AuthorizeAsync(httpContext.User, ShippingPermissions.ManageShipments))
            return httpContext.ChallengeOrForbidApi();

        // Return the ShippingOrderPart snapshot from the order item.
        var order = await contentManager.GetAsync(orderContentItemId);
        if (order is null) return TypedResults.NotFound();

        if (!order.TryGet<ShippingOrderPart>(out var orderPart)) return TypedResults.NotFound();
        return TypedResults.Ok(orderPart);
    }

    private static async Task<IResult> CreateShipmentAsync(
        [FromRoute] string orderContentItemId,
        [FromBody] CreateShipmentRequest request,
        [FromServices] IAuthorizationService authorizationService,
        [FromServices] IContentManager contentManager,
        HttpContext httpContext)
    {
        if (!await authorizationService.AuthorizeAsync(httpContext.User, ShippingPermissions.ManageShipments))
            return httpContext.ChallengeOrForbidApi();

        var shipmentItem = await contentManager.NewAsync("Shipment");
        if (!shipmentItem.TryGet<ShipmentPart>(out var shipmentPart)) shipmentPart = new ShipmentPart();
        shipmentPart.OrderId = new OrchardCore.ContentFields.Fields.TextField { Text = orderContentItemId };
        shipmentPart.QuoteId = new OrchardCore.ContentFields.Fields.TextField { Text = request.QuoteId };
        shipmentPart.ProviderConnectionId = new OrchardCore.ContentFields.Fields.TextField { Text = request.ProviderConnectionId };
        shipmentPart.ServiceId = new OrchardCore.ContentFields.Fields.TextField { Text = request.ServiceId };
        shipmentPart.PurchaseStatus = new OrchardCore.ContentFields.Fields.TextField
        {
            Text = ShipmentPurchaseStatus.NotPurchased.ToString(),
        };
        shipmentPart.FulfillmentStatus = new OrchardCore.ContentFields.Fields.TextField
        {
            Text = ShipmentFulfillmentStatus.Pending.ToString(),
        };
        shipmentItem.Apply(shipmentPart);

        await contentManager.CreateAsync(shipmentItem);

        return TypedResults.Created(
            $"api/commerce/shipments/{shipmentItem.ContentItemId}",
            shipmentItem);
    }

    private static async Task<IResult> GetShipmentAsync(
        [FromRoute] string shipmentId,
        [FromServices] IAuthorizationService authorizationService,
        [FromServices] IContentManager contentManager,
        HttpContext httpContext)
    {
        if (!await authorizationService.AuthorizeAsync(httpContext.User, ShippingPermissions.ManageShipments))
            return httpContext.ChallengeOrForbidApi();

        var item = await contentManager.GetAsync(shipmentId);
        return item is null ? TypedResults.NotFound() : TypedResults.Ok(item);
    }

    private static async Task<IResult> PurchaseShipmentAsync(
        [FromRoute] string shipmentId,
        [FromServices] IAuthorizationService authorizationService,
        [FromServices] IContentManager contentManager,
        [FromServices] Services.IShipmentPurchaseService purchaseService,
        HttpContext httpContext)
    {
        if (!await authorizationService.AuthorizeAsync(httpContext.User, ShippingPermissions.ManageShipments))
            return httpContext.ChallengeOrForbidApi();

        var result = await purchaseService.PurchaseAsync(shipmentId, httpContext.RequestAborted);
        return result.Outcome switch
        {
            Services.ShipmentPurchaseOutcome.Purchased or Services.ShipmentPurchaseOutcome.AlreadyPurchased =>
                (IResult)TypedResults.Ok(new { result.TrackingNumber, result.TrackingUrl }),
            Services.ShipmentPurchaseOutcome.NotFound => TypedResults.NotFound(),
            Services.ShipmentPurchaseOutcome.Blocked or Services.ShipmentPurchaseOutcome.Uncertain =>
                TypedResults.Conflict(result.Message),
            _ => TypedResults.BadRequest(result.Message),
        };
    }

    private static async Task<IResult> CancelShipmentAsync(
        [FromRoute] string shipmentId,
        [FromServices] IAuthorizationService authorizationService,
        [FromServices] IContentManager contentManager,
        [FromServices] IShippingProviderRegistry providerRegistry,
        [FromServices] Services.IShippingCredentialsService credentialsService,
        HttpContext httpContext)
    {
        if (!await authorizationService.AuthorizeAsync(httpContext.User, ShippingPermissions.ManageShipments))
            return httpContext.ChallengeOrForbidApi();

        var shipmentItem = await contentManager.GetAsync(shipmentId);
        if (shipmentItem is null) return TypedResults.NotFound();

        shipmentItem.TryGet<ShipmentPart>(out var shipmentPart);
        var connectionItem = await contentManager.GetAsync(shipmentPart?.ProviderConnectionId.Text ?? string.Empty);
        ShippingProviderConnectionPart? connectionPart = null;
        connectionItem?.TryGet<ShippingProviderConnectionPart>(out connectionPart);

        if (connectionPart is null || providerRegistry.GetProvider(connectionPart.ProviderKey.Text) is not IShipmentProvider provider)
            return TypedResults.BadRequest("Provider not available.");

        var credentials = await credentialsService.GetCredentialsAsync(shipmentPart!.ProviderConnectionId.Text);
        var context = new ShippingProviderConnectionContext(shipmentPart.ProviderConnectionId.Text, credentials);
        var cancelled = await provider.CancelShipmentAsync(context, shipmentPart.ShipmentId.Text ?? string.Empty);

        if (cancelled)
        {
            shipmentPart.FulfillmentStatus = new OrchardCore.ContentFields.Fields.TextField
            {
                Text = ShipmentFulfillmentStatus.Cancelled.ToString(),
            };
            shipmentItem.Apply(shipmentPart);
            await contentManager.UpdateAsync(shipmentItem);
            await contentManager.PublishAsync(shipmentItem);
        }

        return TypedResults.Ok(new { Cancelled = cancelled });
    }

    private static Task<IResult> RefreshTrackingAsync(
        [FromRoute] string shipmentId,
        [FromServices] IAuthorizationService authorizationService,
        HttpContext httpContext) =>
        // Tracking refresh is provider-specific; placeholder for provider implementation.
        Task.FromResult<IResult>(TypedResults.Accepted((string?)null, new { Message = "Tracking refresh scheduled." }));

    private static async Task<IResult> GetLabelAsync(
        [FromRoute] string shipmentId,
        [FromRoute] string labelId,
        [FromServices] IAuthorizationService authorizationService,
        [FromServices] IShippingDocumentStore documentStore,
        HttpContext httpContext)
    {
        if (!await authorizationService.AuthorizeAsync(httpContext.User, ShippingPermissions.ManageShipments))
            return httpContext.ChallengeOrForbidApi();

        var bytes = await documentStore.GetDocumentAsync(labelId);
        if (bytes is null) return TypedResults.NotFound();

        return TypedResults.File(bytes, "application/pdf", $"label-{shipmentId}.pdf");
    }

    private static System.Collections.Generic.Dictionary<string, string> TryDeserializeDict(string? json)
    {
        if (string.IsNullOrWhiteSpace(json)) return [];
        try { return System.Text.Json.JsonSerializer.Deserialize<System.Collections.Generic.Dictionary<string, string>>(json) ?? []; }
        catch { return []; }
    }

    private static T? TryDeserialize<T>(string? json) where T : class
    {
        if (string.IsNullOrWhiteSpace(json)) return null;
        try { return System.Text.Json.JsonSerializer.Deserialize<T>(json); }
        catch { return null; }
    }
}

public sealed record CreateShipmentRequest(
    string QuoteId,
    string ProviderConnectionId,
    string ServiceId);
