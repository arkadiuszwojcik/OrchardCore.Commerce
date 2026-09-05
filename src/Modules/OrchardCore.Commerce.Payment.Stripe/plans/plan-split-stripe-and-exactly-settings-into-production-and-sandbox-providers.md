# 🎯 Split Stripe and Exactly settings into Production and Sandbox providers

## Context
Stripe and Exactly each store one credential set per tenant (`StripeApiSettings`, `ExactlySettings`) and register a single `IPaymentProvider`. Dummy payment / `DummyStripeServices` stay unchanged.

Decisions:
- Register **two unkeyed `IPaymentProvider` instances** per gateway. Production names stay `stripe` and `Exactly`. Sandbox names: `stripeSandbox`, `ExactlySandbox`.
- Hide a provider when that environment’s credentials are incomplete (`CreatePaymentProviderDataAsync` returns null).
- Webhook stays **`/stripe-webhook`**. Query param `environment` is `production` or `sandbox`. Missing/empty means **Production**.
- **Keyed services** (`PaymentEnvironment` as key) are only classes that read env-specific settings or call the gateway with those credentials.
- Do **not** extract a new client from `IStripePaymentService`. Stripe HTTP is already in `IStripePaymentIntentService` / `IRequestOptionsService`. Register `IStripePaymentService` as keyed **only as a wrapper** so each instance holds the matching keyed intent client. No new abstraction, no method-signature split.
- On load, **copy legacy keys into Production**; Sandbox starts empty.
- Do **not** stamp environment on orders.

Checkout calls every provider in one request. Do not use a request-scoped “current environment”. Each provider resolves keyed clients for its `PaymentEnvironment`.

## Shared types (`OrchardCore.Commerce.Payment`)
Add `PaymentEnvironment { Production, Sandbox }` under `OrchardCore.Commerce.Payment.Abstractions`.
Add a name helper: sandbox → `productionName + "Sandbox"`, otherwise unchanged.

## What is keyed vs not

**Keyed** (`AddKeyedScoped`, key = `PaymentEnvironment`), ctor takes `[ServiceKey] PaymentEnvironment`:

Stripe API / credentials:
- `IRequestOptionsService` / `RequestOptionsService` (secret key, account id). Cache `RequestOptions` on that instance.
- `IStripePaymentIntentService`
- `IStripeCustomerService`
- `IStripeSessionService`
- `IStripeSubscriptionService`
- `IStripeConfirmationTokenService`
- `IStripePaymentService` — **wrapper only**, same class as today. Keyed so it injects the same-key `IStripePaymentIntentService`. Do not split order logic out of it.

Exactly API / credentials:
- `IExactlyApi` (Refit client: base address)
- `ExactlyApiHandler` (API key)
- `IExactlyService` — wrapper around the keyed API, same as StripePaymentService.

**Not keyed:**
- `IPaymentProvider` — two `AddScoped<IPaymentProvider>` factories; each takes `PaymentEnvironment` and `GetRequiredKeyedService<T>(environment)`.
- `IPaymentService`, orders, cart, YesSql `OrderPayment`.
- `WebhookController`, `StripeController`, `ExactlyController` — resolve keyed services after they know the environment.
- Persistence, display drivers, settings models.

Dummy Stripe: when `DummyStripeServices` is on, register keyed dummies for **both** keys.

Same-key injection: a keyed `StripePaymentIntentService` should receive the keyed `IRequestOptionsService` for the same `PaymentEnvironment` (`[ServiceKey]` + `GetRequiredKeyedService` or keyed ctor injection). Do **not** also register non-keyed copies of these interfaces, or the wrong instance can be injected.

Non-checkout Stripe entry points with no environment (existing subscription admin, etc.) resolve `PaymentEnvironment.Production`.

## Stripe settings
Replace flat `StripeApiSettings` with:
- `StripeApiEnvironmentSettings`: `PublishableKey`, `SecretKey`, `AccountId`, `WebhookSigningSecret` + existing encrypt/decrypt helpers
- `StripeApiSettings.Production` / `Sandbox`

Keep obsolete root properties only to deserialize old site JSON. On read, if Production is empty and legacy keys exist, copy them into Production; clear legacy fields on save.

Admin UI: two labeled groups, same four fields each; independent empty-after-save for secrets.

Show webhook URLs: `~/stripe-webhook` (Production) and `~/stripe-webhook?environment=sandbox` (Sandbox).

## Stripe runtime
- `StripePaymentProvider`: ctor `PaymentEnvironment` + keyed `IStripePaymentIntentService` / `IStripePaymentService`; `Name` is `stripe` or `stripeSandbox`; publishable key from that environment’s settings; return null if keys missing.
- `IPaymentIntentPersistence`: key session/cookie by shopping cart **and** environment/provider.
- `CheckoutStripeSandbox.cshtml`: reuse Stripe UI with unique DOM ids, pay-button class, error container, JS selectors (`stripe-payment-form.js` already allows overrides). Heading e.g. “Stripe Payment (Sandbox)”.
- Validate URL: `checkout/validate/stripeSandbox`.
- `stripe/middleware` and `stripe/params`: accept provider name/environment (default `stripe`); `GetRequiredKeyedService` for that key.

## Stripe webhook
Keep `[Route("stripe-webhook")]`.

Read `environment` query (`production` | `sandbox`, case-insensitive); missing/unknown → Production. Resolve keyed clients for that key. Use **only that environment’s** `WebhookSigningSecret`. Handlers that call Stripe must use those keyed instances (controller resolves and passes them, or handlers take `IServiceProvider` + environment).

Dashboard:
- Live: `https://{host}/stripe-webhook`
- Test: `https://{host}/stripe-webhook?environment=sandbox`

## Exactly
Same Production/Sandbox settings: `BaseAddress`, `ProjectId`, `ApiKey`. Migrate current values into Production. Expose `BaseAddress` in admin UI per environment.

Keyed Refit client + handler per environment. Two unkeyed `ExactlyPaymentProvider`s wrapping keyed `IExactlyService`. `CheckoutExactlySandbox.cshtml` with unique pay-button class; controller resolves keyed `IExactlyService` from provider name. Verify-API uses a chosen environment’s keyed client.

## Tests / recipes
Update settings tests, UI tests, and recipes so old flat JSON maps to `Production`. Coverage: sandbox provider hidden without keys; both providers appear when both configured; webhook `?environment=sandbox` uses sandbox keyed client/secret; omitted query param uses Production.

## Risks
- Two Stripe Elements on one checkout page: unique ids/selectors required (`#payment-form_payment`, `#StripePaymentPart_PaymentIntentId_Text`).
- Registering a non-keyed `IStripePaymentService` besides the keyed ones will break injection.
- Existing Dashboard webhooks with no query param stay Production. Sandbox needs `?environment=sandbox` on the Test endpoint.

**Last Updated**: 2026-09-04 21:31:07

## 📝 Plan Steps
-  **Add `PaymentEnvironment` and provider-name helper in `OrchardCore.Commerce.Payment`.**
-  **Split `StripeApiSettings` / view model / edit view / display driver; migrate legacy keys into Production; show webhook URLs including the sandbox query param.**
-  **Register Stripe API clients as keyed services (`IRequestOptionsService`, payment intent/customer/session/subscription/confirmation). Register `IStripePaymentService` as keyed wrapper only (same class, same-key intent service). Key PaymentIntent persistence by environment. Do not add a new Stripe client type.**
-  **Register Production and Sandbox `StripePaymentProvider` instances that consume keyed clients; add `CheckoutStripeSandbox` view and unique client ids/URLs.**
-  **Update `WebhookController` to resolve environment from `environment` query (default Production) and `GetRequiredKeyedService` for that key. Use only that environment’s signing secret.**
-  **Split `ExactlySettings` / UI; keyed `IExactlyApi` / `IExactlyService` per environment; two `ExactlyPaymentProvider`s and a sandbox checkout view.**
-  **Update tests, recipes, Dummy Stripe keyed registrations, and settings configuration so existing tenants keep working with Production-only keys.**

