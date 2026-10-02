# 2. Seller ownership: a request-scoped global scope, not a model-wide one

Date: 2026-09-28
Status: Accepted

## Context

TDD §6.4 rule 4 states: "every seller-owned table carries seller_id/
store_id and is queried through a global Eloquent scope (§8.1) so a
missing WHERE clause in application code cannot leak cross-seller data."
§8.5 sharpens this with a concrete requirement: "a seller's API token
cannot retrieve another seller's order_groups or settlements even with a
guessed ID (404, not 403, to avoid confirming resource existence)."

Taken literally, "a global Eloquent scope" suggests
`Product::addGlobalScope(...)` registered unconditionally in the model's
`booted()` method. That would be wrong here: `Product` is read by buyers
browsing the storefront and by admins moderating the catalogue, neither
of whom is "a seller" the row should be scoped to. A blanket global scope
keyed to "the currently authenticated seller" would make every public
product listing return empty results for anyone who isn't that exact
seller.

## Decision

The global scope (`App\Models\Scopes\SellerOwnershipScope`) is real and is
a genuine Eloquent `Scope` implementation, but it is registered
per-request, only for requests going through the seller dashboard's API
surface (`/api/v1/seller/*`), by
`App\Http\Middleware\ScopeQueriesToActingSeller`. Public and admin routes
never load this middleware, so `Product::published()`-style storefront
queries and admin moderation queries are never scoped by it.

Each seller-owned model (`Product`, `ProductVariant`) implements its own
`scopeOwnedBySeller()` local scope via the `App\Models\Concerns\SellerOwned`
trait, since the path back to `seller_id` differs per table (`Product`
goes through `store_id`; `ProductVariant` goes through
`product.store_id`). The global scope class just forwards to whichever
local scope the concrete model defines — one scope class, N ownership
paths.

To get the §8.5 "404, not 403" behaviour specifically, the middleware is
also inserted into Laravel's middleware **priority** list ahead of
`SubstituteBindings` (`bootstrap/app.php`). Route-model binding
(`{product}` in a seller route) therefore runs the scoped query — a
product belonging to another seller simply isn't found, producing Laravel's
ordinary `ModelNotFoundException` → 404, before the controller or any
Policy ever sees it. Without this priority ordering, `SubstituteBindings`
would resolve the model unscoped and only the Policy's explicit ownership
check would catch it, giving 403 instead — confirming the resource exists
under a different owner, which is exactly what §8.5 says not to do.

## Alternatives considered

- **Unconditional global scope on `Product`.** Rejected outright — breaks
  every buyer- and admin-facing query, as above.
- **No global scope; ownership enforced only by each Policy's explicit
  check** (what Run 1.1's `RoleAssignmentPolicy` does). Rejected as the
  sole mechanism: it satisfies authorization but not the "missing WHERE
  clause cannot leak" property TDD §6.4 asks for — a seller-dashboard
  controller method that lists "my products" via a bare `Product::all()`
  would still leak every seller's catalogue. The middleware scope is the
  backstop for exactly that class of bug.
- **A `seller_id` scope keyed by route parameter** (e.g. requiring every
  seller route to be nested under `/sellers/{seller}/products`) instead of
  "the acting seller." Rejected: TDD's endpoint list (§7.3) has seller
  routes un-nested (`POST /api/v1/seller/products`), authorizing off the
  authenticated token, not a URL-supplied seller ID — matching §5.4's
  broader rule that authorization is "parameterized by the authenticated
  session ... never by an ID extracted from" the request.

## Consequences

- Every future seller-owned table (e.g. `settlements` in Run 1.5, TDD
  §8.5's own example) adopts the same pattern: implement `SellerOwned`,
  register its middleware group with `seller.scope`, done — no new
  authorization mechanism to design.
- This relies on Eloquent global scopes being process-lifetime-safe under
  PHP-FPM's one-process-per-request model (true for the Hostinger launch
  topology, TDD §2.4). If the platform later adopts Laravel Octane
  (persistent workers) per the scalability roadmap's later stages, a
  scope added via `addGlobalScope` in one request could leak into the
  next unless explicitly cleared — `ScopeQueriesToActingSeller` would then
  need a `terminate()` step (or Octane's `flush` hook) to remove it. Flagged
  here rather than solved now, since Octane isn't part of TDD §13's
  scalability stages.
