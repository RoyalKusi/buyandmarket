# 4. "Only active sellers can list products" gates publishing, not drafting

Date: 2026-09-28
Status: Accepted

## Context

Run 1.2 implemented TDD §3.1 module 2's rule — "only `active` sellers can
list products" — as a gate on product *creation*: `ProductService::create()`
rejected any seller whose status wasn't `active`.

Building Run 1.3 (seller onboarding) surfaced a direct conflict with that
reading. TDD §3.1 module 4's onboarding stepper is: "business info -> KYC
documents -> bank/payout details -> store setup -> **first product** ->
admin review." A seller only reaches `active` status as the *outcome* of
admin review (the stepper's last step) — so by construction, the "first
product" step must happen while the seller is still `pending` or
`under_review`, never `active`. Run 1.2's gate made that step impossible:
a seller could never produce the "first product" needed to complete
onboarding, because product creation itself demanded the status onboarding
was trying to reach.

## Decision

"List" is read as *publish* (make purchasable/publicly visible), not
*create*. The two lifecycles are separate and now gated separately:

- `ProductPolicy::create()` requires only that the seller is in "good
  standing" (`Seller::isInGoodStanding()`: not `suspended` or
  `terminated`) — any seller mid-onboarding, including one still
  `pending`, may create a `draft` product.
- `ProductService::approve()` (the `pending_review -> published`
  transition) now requires `$product->store->seller->isActive()`. This is
  where "only active sellers can list products" actually applies — a
  product cannot become publicly purchasable unless its seller is fully
  active.

## Alternatives considered

- **Move "first product" earlier in the stepper, before it needs to
  exist.** Rejected — the stepper's order is specified in the TDD; this
  would be reordering the document's own requirement rather than
  correctly interpreting it, and "first product" logically depends on
  store setup being already complete (a product must belong to a store).
- **Let onboarding fabricate an "active-enough" status just for product
  creation.** Rejected — this would either weaken the `active` status's
  meaning elsewhere (settlements, badges, search visibility all key off
  it) or require a second, parallel status field duplicating the same
  concept.

## Consequences

- Run 1.2's original test (`Seller::factory()->create(['status' =>
  'pending'])` expecting product creation to be forbidden) was replaced
  with two more precise tests: a seller mid-onboarding (`under_review`)
  *can* create a draft product; a `suspended` seller cannot. A new test
  also confirms a `pending_review -> published` transition is refused
  for a non-active seller's product.
- Any future module that reads "only active sellers can X" from the TDD
  should ask the same question this one did: does X mean *create/own* or
  *make live to buyers*? The pattern established here — good-standing
  gates ownership actions, `isActive()` gates public-visibility
  transitions — is the default answer going forward.
