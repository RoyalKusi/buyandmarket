# 5. PaymentGatewayManager: resolving between simultaneous live gateways

Date: 2026-09-28
Status: Accepted

## Context

TDD §2.1's non-negotiable interface seam is `PaymentGateway`, framed as "a
second provider is a second class" — implying a single container binding
of the interface that swaps wholesale (e.g. per environment, or over
time as the platform migrates providers). Run 1.5 adds Pesepay as the
first implementation; the user then asked for Paynow as well, as a
second, simultaneously-live implementation the buyer chooses between at
checkout (TDD §5.9's PaymentProcessing step), not a replacement.

A single `app()->bind(PaymentGateway::class, ...)` binding can't represent
"the buyer picks Pesepay or Paynow on this specific checkout" — it only
ever resolves to one concrete class per request.

## Decision

`App\Services\Payments\PaymentGatewayManager` is a small factory/manager,
not a container binding of `PaymentGateway` itself:

```php
class PaymentGatewayManager
{
    public function for(string $provider): PaymentGateway { ... }
    public function availableProviders(): array { return ['pesepay', 'paynow']; }
}
```

Callers (`CheckoutService::initiatePayment()`, `PaymentWebhookController`)
resolve `PaymentGatewayManager` from the container as usual, then call
`->for($provider)` with the provider the buyer selected (checkout
payload) or the URL (`/webhooks/{provider}`). Each concrete gateway
(`PesepayGateway`, `PaynowGateway`) still depends on nothing but the
`PaymentGateway` interface's contract — `PaymentGatewayManager` is the
only place that knows both exist.

## Alternatives considered

- **A single `PaymentGateway` binding, chosen by config/environment.**
  Rejected outright: it cannot express "the buyer chooses at checkout,"
  which is the actual requirement — Pesepay and Paynow are both live
  simultaneously, not environment-gated alternatives.
- **Two separate, ad-hoc container bindings resolved by string key
  (`app("payment.$provider")`).** Rejected: scatters provider-name
  string-matching across every call site instead of one place, and gives
  no single spot to enumerate `availableProviders()` for validation
  (`CheckoutController::initiatePayment`'s `in:pesepay,paynow` rule) or to
  extend later.

## Consequences

- `PaymentGateway` itself stays exactly the seam the TDD specifies —
  `PaymentGatewayManager` sits *above* it, not instead of it; a new
  provider is still "a second class" (implements `PaymentGateway`), just
  also registered in the manager's `match()`.
- Every call site that needs a gateway takes a `string $provider`
  parameter through to `PaymentGatewayManager::for()`, rather than typing
  `PaymentGateway` directly — this is the one deliberate deviation from
  "depend on the interface" as normally practiced, and is confined to the
  two call sites above.
- Adding a third provider is additive: a new `Foo GatewayClass` plus one
  `match()` arm and one `availableProviders()` entry — no change to
  `PaymentGateway`, `CheckoutService`, or the webhook route's `whereIn`
  beyond adding its name.
