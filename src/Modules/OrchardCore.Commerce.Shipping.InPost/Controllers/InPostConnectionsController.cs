using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using OrchardCore.Admin;
using OrchardCore.Commerce.Shipping.Abstractions;
using OrchardCore.Commerce.Shipping.InPost.Client;
using OrchardCore.Commerce.Shipping.InPost.Models;
using OrchardCore.Commerce.Shipping.Permissions;
using OrchardCore.Commerce.Shipping.Services;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;

namespace OrchardCore.Commerce.Shipping.InPost.Controllers;

/// <summary>
/// Backs the "Test connection" button of the InPost connection editor. It calls the ShipX organization endpoint with
/// the values currently typed into the editor, so a connection can be checked before it is saved.
/// </summary>
[Admin]
public class InPostConnectionsController : Controller
{
    private readonly IAuthorizationService _authorizationService;
    private readonly InPostApiClient _client;
    private readonly IShippingCredentialsService _credentialsService;

    public InPostConnectionsController(
        IAuthorizationService authorizationService,
        InPostApiClient client,
        IShippingCredentialsService credentialsService)
    {
        _authorizationService = authorizationService;
        _client = client;
        _credentialsService = credentialsService;
    }

    [HttpPost]
    [ValidateAntiForgeryToken]
    public async Task<IActionResult> TestOrganization(
        [FromForm] InPostOrganizationTestRequest request,
        CancellationToken cancellationToken)
    {
        if (!await _authorizationService.AuthorizeAsync(User, ShippingPermissions.ManageShippingProviderConnections))
        {
            return Forbid();
        }

        // The ID goes into the request path, so only digits are accepted.
        var organizationId = request.OrganizationId?.Trim();
        if (string.IsNullOrEmpty(organizationId) || !organizationId.All(char.IsAsciiDigit))
        {
            return Failure("Enter a numeric organization ID.");
        }

        // A blank token on an existing connection means "keep the stored one", so test with that.
        var apiToken = request.ApiToken?.Trim();
        if (string.IsNullOrEmpty(apiToken) && !string.IsNullOrEmpty(request.ConnectionId))
        {
            var stored = await _credentialsService.GetCredentialsAsync(request.ConnectionId);
            apiToken = stored.TryGetValue(InPostConnection.ApiTokenKey, out var storedToken) ? storedToken : null;
        }

        if (string.IsNullOrEmpty(apiToken))
        {
            return Failure("Enter the API token.");
        }

        var context = new ShippingProviderConnectionContext(
            "inpost-connection-test",
            new Dictionary<string, string> { [InPostConnection.ApiTokenKey] = apiToken },
            Metadata: new Dictionary<string, object>
            {
                [nameof(InPostConnectionSettings)] = new InPostConnectionSettings { OrganizationId = organizationId },
            },
            IsTestMode: request.IsTestMode);
        var requestUrl = $"{InPostConnection.GetBaseUrl(context)}/v1/organizations/{organizationId}";

        try
        {
            // The raw response is shown as is, so the popup reflects exactly what ShipX returned.
            var result = await _client.GetOrganizationJsonAsync(context, cancellationToken);
            if (result.ValueKind is JsonValueKind.Undefined or JsonValueKind.Null)
            {
                return Json(new { success = false, requestUrl, httpStatus = 200, error = "The ShipX API returned an empty response." });
            }

            return Json(new { success = true, requestUrl, httpStatus = 200, result });
        }
        catch (InPostApiException exception)
        {
            return Json(new { success = false, requestUrl, httpStatus = (int)exception.StatusCode, error = exception.Message });
        }
        catch (HttpRequestException exception)
        {
            return Json(new { success = false, requestUrl, error = $"The ShipX API could not be reached: {exception.Message}" });
        }
        catch (TaskCanceledException)
        {
            return Json(new { success = false, requestUrl, error = "The ShipX API did not answer in time." });
        }
    }

    private JsonResult Failure(string error) => Json(new { success = false, error });
}

public class InPostOrganizationTestRequest
{
    public string? ConnectionId { get; set; }
    public string? OrganizationId { get; set; }
    public string? ApiToken { get; set; }
    public bool IsTestMode { get; set; }
}
