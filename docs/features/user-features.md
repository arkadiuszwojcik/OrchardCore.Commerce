# User Features

## Addresses

During checkout you can save the shipping and billing addresses for later use. This can be accessed in the `/user/addresses` page. You can also fill these before checkout, perhaps direct the user to this page after registration.

## Permissions

By default all permissions are admin-only. This is fine for the management permissions (Manage Currency Settings, Manage Stripe API Settings, Manage Region Settings).

There is also the _Ability to checkout_ permission. This is required to complete a checkout. It's also admin-only to avoid being too permissive. You should add it to the role you want to be able to purchase (typically this is the _Authenticated_ role).

There is also the _Use Sandbox Checkout Mode_ permission (`UseSandboxCheckout`). When a user has this permission, checkout displays an "Enable sandbox/test mode for this order" checkbox. Enabling it sets the `IsTestOrder` flag on the order and routes supported payment providers (currently TPay and Przelewy24) to their sandbox environments instead of production; the same flag is intended to be reused for shipping providers' sandbox environments in the future. This is meant for a dedicated _Tester_ role used to validate payment and shipping integrations without processing real transactions or shipments. The flag is re-validated server-side on every checkout submission, so it cannot be enabled by tampering with the posted form unless the user actually holds the permission.
