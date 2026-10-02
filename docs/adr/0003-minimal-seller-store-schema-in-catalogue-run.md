# 3. Minimal `sellers`/`stores` schema ships with Catalogue, not Onboarding

Date: 2026-09-28
Status: Accepted

## Context

TDD §14's run backlog orders Run 1.2 ("Catalogue core") before Run 1.3
("Seller onboarding"), with Run 1.2's own exit criterion reading "a seller
can create a product with variants." But `products.store_id` is a
required foreign key to `stores`, and `stores.seller_id` to `sellers`
(TDD §6.2) — tables the module numbering places under "Identity and
accounts" (§3.1, modules 1-6), the same modules Run 1.3 is titled after.
Building Run 1.2 strictly in isolation, with no `sellers`/`stores` tables
at all, would make its own exit criterion impossible to satisfy.

## Decision

Run 1.2 creates minimal `sellers` and `stores` tables — only the columns
TDD §6.2's core entity reference lists for them (`sellers`: user_id,
business_name, status, kyc_status; `stores`: seller_id, slug, name,
banner_path) — as a foundation the catalogue's foreign keys need to exist
at all.

It does **not** build any of Run 1.3's actual scope: no
`seller_onboarding_steps` (module 4, the registration stepper), no
`kyc_documents`/`kyc_reviews` (module 5), no `seller_badges` (module 6,
the nightly badge computation job). A seller reaches `active` status in
this run's tests only by direct factory state
(`Seller::factory()->active()`) — there is no HTTP path from registration
to an active seller yet. That path, with its KYC review flow and audit
trail, is exactly what Run 1.3 adds on top of this schema.

## Alternatives considered

- **Build Run 1.3 first, out of its documented order.** Rejected: the
  phase plan (§14) is explicit about run ordering, and Run 1.3's own scope
  (onboarding stepper, KYC document upload/review, badge computation) is
  unrelated to what Run 1.2 needs — pulling all of it forward would be
  "reaching ahead into a later run's modules" in the build directive's own
  terms, for no benefit to this run's exit criterion.
- **Give `products` a bare `seller_id` for now and add `store_id` later**
  (expand-migrate-contract across runs). Rejected: TDD's schema is
  unambiguous that a product belongs to a *store*, not directly to a
  seller (`stores` exists specifically to model "one store per seller,"
  §3.1 module 3, with its own slug/branding) — skipping it would mean
  redoing the relationship shortly after, and every other document
  reference (URLs, SEO slugs, §10.7) treats the store as the storefront
  identity, not the seller.

## Consequences

- Run 1.3 extends `sellers`/`stores` additively (new nullable columns,
  new related tables for onboarding/KYC/badges) — never renames or drops
  anything this run ships, keeping every migration here a normal,
  backward-compatible deploy per TDD §12.2.
- Any test or seeder needing an "active seller" before Run 1.3 exists
  uses the `Seller::factory()->active()` state (see
  `database/factories/SellerFactory.php`), which also provisions a
  `Store` — this is a testing convenience standing in for the onboarding
  flow, not a claim that the flow exists.
