# Changelog

All notable changes to BuyAndMarket v2 are documented here, grouped by
build run (see `docs/adr/` for the architectural decisions behind them).

## Run 1.4 — Storefront

Scope: TDD §14, stage 1.4 — homepage, search, category, PDP, store pages
(Design System §6.1-6.5), including skeleton and empty/error states
(Design System §5.3-5.4).

### Added

- `App\Contracts\SearchProvider` + `App\Services\Search\
  EloquentSearchProvider` (TDD §2.1 non-negotiable: an interface from day
  one, even with one implementation). Uses MySQL `FULLTEXT` (via
  `whereFullText()`, against the index Run 1.2's products migration
  already added) with a portable `LIKE` fallback for any other driver —
  Eloquent's own base grammar throws for drivers that don't implement
  full-text search, so the fallback is what keeps the exact same call
  site working under the SQLite database this app's tests run on. Only
  published products from stores whose seller is `active` are ever
  returned (TDD §5.2's retrieval-visibility rule, applied here too, not
  just in the future AI/RAG path).
  - Bound in `AppServiceProvider` — swapping to Meilisearch/Typesense
    later (TDD §13 stage 4) is a second implementation and a one-line
    binding change, never a call-site change.
- `App\Livewire\ProductGrid`: the shared filter/sort/paginate component
  Design System §6.3 calls for ("a category page is architecturally
  search pre-scoped to one category — one component set serves both").
  Mounted with a locked `categoryId` (category pages), a locked
  `storeId` (store pages), or free-text `q` (search) and reused across
  all three page types.
- Storefront routes/controllers/views for the homepage, search results,
  category pages, PDP, and store pages (`App\Http\Controllers\
  Storefront\*`), all against the exact Tailwind tokens from Run 1.1's
  `tailwind.config.js` — no arbitrary values anywhere in the new Blade
  templates.
- `<x-product-tile>` (Design System §4.3, "the single most repeated
  component"), `<x-store-card>`, `<x-breadcrumbs>`, `<x-empty-state>`
  (§5.4: illustration + heading + one clear action, never a bare "no
  results"), and `<x-skeleton.product-tile>` (§5.3: exact box model of
  the real tile, `prefers-reduced-motion`-aware shimmer per §5.2) as
  reusable Blade components.
- Products gained a `slug` column (additive migration, TDD §12.2) and
  `App\Services\ProductService` now generates one from the title at
  creation — TDD §6.2's products entity reference doesn't list a slug,
  but the storefront needs stable, readable PDP URLs; the API keeps
  id-based route binding unchanged (`{product:slug}` is opt-in per web
  route, not a global model change).

### Verified against acceptance criteria (TDD §16 / Run 1.4 exit criteria)

- "A buyer can browse and reach a PDP entirely through the built UI":
  `BrowseToPdpTest` drives three independent paths — homepage → store →
  PDP, category page → PDP, search → PDP — all through real HTTP
  requests against real rendered HTML, plus confirms a draft product
  is unreachable (403) from the public storefront.
- `ProductGridTest` covers the search/filter contract directly: only
  published products from active sellers' stores are ever returned,
  text search matches, category/price filters narrow correctly, and a
  zero-result query renders the empty state.
- 61 tests / 155 assertions passing (9 new); Pint clean; migrations run
  clean fresh and reversible; production CSS/JS build still measures
  38.86KB gzipped JS (unchanged — no new JS shipped) against the TDD
  §9.1 100KB budget.
- Two real bugs caught running the suite before they shipped: a Blade
  attribute expression with escaped nested quotes that silently broke
  template compilation, and `whereFullText()`'s assumption that it
  degrades gracefully on every database driver (it doesn't — only
  MySQL/PostgreSQL's grammars implement it; SQLite's throws). Both
  fixed; the fix is documented inline in `EloquentSearchProvider`.

### Deferred / flagged for a later run

Every omission below is called out inline, in the view/controller that
omits it, not just here — each is a real gap, not a silently-lowered bar:

- Homepage: hero carousel, "Deals near you" (geolocation ranking),
  AI-curated "Picked for you", sponsored placement block, trust strip —
  each needs a module (campaign content, geolocation, AI/RAG, ads,
  aggregate trust stats) this build hasn't reached yet.
- Header: category mega-menu, AI-assistant entry icon, account/wishlist/
  cart icon cluster (cart and wishlist have no backing module until Run
  1.5/§3.5); mobile bottom tab bar, for the same reason.
- PDP: real image gallery (Run 1.9's image pipeline), rating row and
  reviews tab content (module 33), related/recently-viewed rails (module
  40), the AI "Ask about this product" entry point (Run 1.8). Add to
  cart / Buy now render per spec but are disabled with an explanatory
  label — never silently inert.
- Store pages: banner image, avatar overlap, Follow/Message buttons, and
  every tab beyond "All Products" (Categories/About/Reviews/Policies).
- Search: the search-as-you-type suggestion dropdown (§6.2) and the
  dual-handle price-range slider (rendered as two plain number inputs
  instead) — both are their own small JS components better built once
  more Alpine patterns exist elsewhere in the storefront.
- Sponsored/ad placements and their frequency capping (§6.9) — modules
  15/16 aren't built.
- axe-core accessibility scanning isn't wired into CI yet (TDD §11) —
  needs a headless-browser step this run didn't set up; the components
  built here follow the Design System's accessibility rules (landmarks,
  `aria-current`, `sr-only` labels, focus-visible rings) but aren't yet
  machine-verified.

## Run 1.3 — Seller onboarding

Scope: TDD §14, stage 1.3 — registration, KYC, store creation (§3.1
modules 1-6).

### Added

- `App\Services\SellerOnboardingService`: the full stepper (business info
  -> KYC documents -> bank/payout details -> store setup -> first product
  -> admin review), each step's completion independently timestamped and
  resumable (`seller_onboarding_steps`). Registration is self-service —
  any authenticated user may become a seller of their own account —
  and always assigns the `buyer`-parallel `seller` role the same way Run
  1.1's registration assigns `buyer`.
- `kyc_documents`/`kyc_reviews`: national ID + proof of address required,
  business registration optional; stored via a dedicated, private
  `Storage::disk('kyc')` (outside the public webroot, TDD §8.3) with
  server-side MIME validation. Every review decision (one row per
  decision, so a rejection-then-resubmission keeps full history) is
  audit-logged.
- Signed, time-limited KYC document downloads
  (`App\Http\Controllers\Api\V1\KycDocumentController`): a Policy-gated
  endpoint mints a 15-minute `temporarySignedRoute`; the actual download
  route carries no other authorization; the signature is the credential
  (TDD §8.3).
- `App\Services\KycReviewService`: approval carries a seller straight from
  `under_review` to `active` in one action (by the time admin review
  happens, every earlier stepper step — including store setup and the
  first product — is already complete, TDD §3.1 module 4); rejection
  requires a reason code, reopens the KYC-documents step, and returns the
  seller to `pending` for resubmission (mirrors Run 1.2's product-rejection
  UX).
- `seller_payout_details`: the stepper's bank/payout step; account number
  encrypted at rest.
- `App\Services\SellerBadgeService` + nightly `sellers:recompute-badges`:
  computes the two badges the current schema can honestly support —
  Verified (KYC approved) and New (<90 days) — as a row's presence, added
  or removed idempotently on each run (TDD §3.1 module 6). Top Rated and
  Fast Responder are deferred (see below).
- `SellerPolicy` with full authorization-matrix coverage: self-service
  registration (once), owner-only onboarding actions, admin-only KYC
  review.

### Corrected

- Run 1.2 gated product **creation** on `seller.status === 'active'`. TDD
  §3.1 module 4's stepper needs a seller to create their "first product"
  *before* reaching `active` (admin review, which grants `active`, is the
  stepper's last step) — so that gate made onboarding impossible to
  complete. Fixed: creation now requires only that the seller isn't
  `suspended`/`terminated`; "only active sellers can list products" is
  enforced instead at the `pending_review -> published` transition, which
  is what "list" (publish) actually means. Full rationale in
  `docs/adr/0004`; Run 1.2's tests were updated to match.

### Verified against acceptance criteria (TDD §16 / Run 1.3 exit criteria)

- "A seller can go from registration to `active` status end-to-end":
  `OnboardingTest::test_the_full_onboarding_flow_takes_a_seller_from_registration_to_active`
  drives every stepper step through its real HTTP endpoint, in order,
  finishing on an admin KYC approval that flips the seller to `active`.
- KYC rejection's "fix and resubmit" path (§4.2) is covered end-to-end:
  reason code required, documents step reopens, seller returns to
  `pending`.
- Full authorization-matrix coverage for every new Policy-protected
  route (self-registration-once, owner-only onboarding actions, admin-only
  review/approval).
- 52 tests / 130 assertions passing (17 new since Run 1.2); Pint clean;
  migrations run clean fresh and reversible.
- Running the full end-to-end flow test caught two real bugs before they
  shipped: `URL::temporarySignedRoute()` was generating a signed URL
  against an unprefixed route name (the actual name carries the `api.v1.`
  group prefix), and `Seller::factory()` didn't create the
  `seller_onboarding_steps` rows the real `register()` flow does — which
  let the "submit for review before all steps are complete" guard pass
  vacuously in tests. Both fixed; the factory now mirrors registration's
  side effects exactly, so factory-built sellers behave like real ones in
  every other test that touches onboarding state.

### Deferred / flagged for a later run

- No virus scanning on KYC document uploads. TDD §8.3 names this
  explicitly ("virus scan on KYC document uploads"); it requires an
  external scanning service (e.g. ClamAV) this run has no integration
  point for. Server-side MIME sniffing and re-encoding are not a
  substitute and aren't claimed to be — this is a real gap, not a
  silently-downgraded requirement.
- Top Rated and Fast Responder badges are not computed — they depend on
  the reviews module and buyer-seller messaging, neither built yet.
  `SellerBadgeService` is structured so adding them later is additive,
  not a rewrite.
- No admin worklist/queue UI for pending seller reviews (TDD §4.4 calls
  for "purpose-built queues ... surfaced as prioritised worklists") —
  that's dashboard UI, Run 1.7's scope. This run's admin actions are
  full-featured API endpoints; only the queue's presentation is deferred.
- `buyer_profiles` (TDD §3.1 module 1's second entity, alongside `users`)
  is still unbuilt. Nothing in Runs 1.1-1.3 needed it yet; it'll ship
  alongside whichever run first needs buyer-specific profile data
  (addresses, preferences) — likely Commerce core (Run 1.5).

## Run 1.2 — Catalogue core

Scope: TDD §14, stage 1.2 — categories, brands, attributes, products,
variants, inventory (§3.2).

### Added

- Minimal `sellers`/`stores` schema (only the columns TDD §6.2 lists for
  them) as the foreign-key foundation the catalogue needs — the actual
  onboarding/KYC/badge workflow is Run 1.3's own scope. Rationale in
  `docs/adr/0003`.
- Category tree (`categories`, self-referencing, `App\Services\
  CategoryService` enforcing the §6.2 max-depth-3 rule at the application
  layer) with category-scoped attribute templates (`category_attributes`).
- Brands (`App\Services\BrandService`): seller-suggested, enter `pending`,
  admin-approved/rejected before appearing in filters (TDD §3.2 module 11).
- Products (`App\Services\ProductService`): `draft -> pending_review ->
  published -> archived` lifecycle (module 7), soft-deleted (never hard-
  deleted, §6.4 rule 3), `DECIMAL(12,2)` pricing, one leaf category per
  product enforced at creation.
- Product variants with attribute-value combinations
  (`variant_attribute_values`), each carrying its own SKU/price
  override/stock.
- `inventory_ledger` (append-only) + `App\Services\InventoryService`:
  every stock change writes a ledger row with a reason code;
  `stock_quantity` on both variant and product is a materialised rollup,
  never written directly (§6.4 rule 2). `inventory:reconcile` (Artisan
  command, scheduled nightly) recomputes stock from the ledger — the
  "checksum job" TDD names explicitly.
- `price_history` (append-only) logging every price change with actor +
  timestamp (module 13), written on both initial product creation and any
  later price update, and audit-logged alongside.
- Seller-ownership enforcement structural to the query layer, not just
  Policy checks (TDD §6.4 rule 4/§8.5): `App\Models\Scopes\
  SellerOwnershipScope`, applied for the duration of a request by
  `App\Http\Middleware\ScopeQueriesToActingSeller` on every
  `/api/v1/seller/*` route, reordered ahead of Laravel's route-model
  binding so a guessed product ID belonging to another seller resolves
  404, never 403 (confirms nothing about who owns it). Full rationale,
  including the alternatives rejected, in `docs/adr/0002`.
- `ProductPolicy`, `CategoryPolicy`, `BrandPolicy` with full
  authorization-matrix test coverage per TDD §11 (admin/seller/buyer/guest
  × every new Policy-protected route).
- `/api/v1` routes: public category listing + product detail (view-scoped
  to published or owner), admin category/brand/product-moderation
  endpoints, seller brand-suggestion/product-CRUD/lifecycle endpoints.

### Verified against acceptance criteria (TDD §16 / Run 1.2 exit criteria)

- "Admin can create a full category tree": `CategoryTest` builds a full
  4-level tree (depths 0-3) and confirms a 5th level (depth 4) is
  rejected.
- "A seller can create a product with variants": `ProductCreationTest`
  covers an active seller creating a multi-variant product end-to-end,
  including the initial price-history and inventory-ledger rows it
  produces; an inactive seller and a non-seller are both denied.
- 35 tests / 87 assertions passing (14 new since Run 1.1); Pint clean;
  migrations run clean fresh and reversible (`migrate:fresh`).

### Deferred / flagged for a later run

- No admin API for directly creating `attributes`/`attribute_values` yet
  — they're modelled and consumed (variants attach to them), but only
  seedable, not yet manageable through an endpoint. Not required by this
  run's exit criteria; will be added alongside whichever run first needs
  sellers to define category-specific attribute sets through the UI.
- No product images. TDD's module 7 name ("Product creation/upload")
  reads as if image upload belongs here, but the full image-intelligence
  pipeline (validation, quality scoring, background processing,
  compression/variants, alt-text) is explicitly Run 1.9's scope — adding
  a bare, un-pipelined image field now would mean either reworking it in
  1.9 or shipping catalogue images that never got a quality/authenticity
  pass. Deferred whole, not half-built.
- No `SearchProvider` interface yet, despite `products.description`
  already having a MySQL-only `FULLTEXT` index. TDD's own module map
  places search (module 39) under Storefront (Run 1.4), which is also
  where a consumer for the interface first exists; building the
  abstraction with nothing calling it yet would be the "interface with no
  second implementation and no first caller" anti-pattern. The index
  itself is schema, not architecture, and costs nothing to have early.
- Larastan/PHPStan still could not be installed in this sandbox (same
  GitHub dist-download network limitation noted in Run 1.1); unaffected
  packages continue to install and run normally.

## Run 1.1 — Foundations

Scope: TDD §14, stage 1.1 — Laravel scaffold, auth/RBAC (§8.1), Design
System token integration (Tailwind config), CI/CD pipeline (§12).

### Added

- Laravel 11 application scaffold (Blade + Livewire + Alpine.js + Tailwind),
  replacing the WordPress/WooCommerce/Dokan stack per TDD §2.1.
- `tailwind.config.js` compiled 1:1 from the Design System's §1–3 and §9
  token file — colours, type scale, spacing, radii, shadows, breakpoints,
  motion durations/easing. No arbitrary Tailwind values are permitted
  outside this file.
- RBAC data model (TDD §8.1, §3.6 modules 31/32): `role_assignments`
  (a user may hold buyer/seller/shipper/admin/sub_admin simultaneously),
  `permissions`, `role_permissions` (fixed-role grants), `admin_roles` +
  `admin_role_permissions` (named, least-privilege sub-admin permission
  sets, e.g. "Catalogue moderator").
- `App\Models\Concerns\HasRoles` — `hasRole()`, `hasAnyRole()`,
  `assignRole()`, `hasPermission()`.
- `audit_logs` table and `App\Services\AuditLogger` (TDD §6.2/§8.9) —
  append-only, written in the same DB transaction as the mutation it
  records. Wired end-to-end for the first privileged action: role
  assignment.
- `RoleAssignmentPolicy` + `POST /api/v1/admin/users/{user}/roles` — the
  first Policy-protected route, with a full authorization-matrix test
  (admin allowed; buyer/seller/shipper/guest denied) per TDD §11.
- A global `Gate::before` admin bypass (`App\Providers\AppServiceProvider`)
  so every future Policy only needs to encode its own least-privilege
  rule, not repeat "unless the actor is admin."
- Laravel Fortify (registration, password reset/update, profile update,
  TOTP two-factor) and Laravel Sanctum (Bearer tokens for mobile/API,
  session-cookie auth for the Livewire web client sharing one
  authorization codepath, TDD §7.1) wired with app-specific actions.
  Registration always assigns the `buyer` role (TDD §3.1 module 1) —
  seller/shipper/admin access is only ever granted via the privileged,
  audit-logged role-assignment path above, never at self-service signup.
- `users` schema per TDD §6.2: `phone` and `email` both unique/nullable
  with an application-layer guard requiring at least one; a `hash_algo`
  column staged for the phpass-legacy lazy-rehash migration path (§10.5).
- Password policy (TDD §8.2): minimum 10 characters, HaveIBeenPwned
  k-anonymity breach check (skipped only in the `testing` environment, so
  CI stays deterministic and network-independent).
- `routes/api.php` mounted at `/api/v1` (TDD §7.1), Sanctum's stateful-API
  middleware enabled for the web client.
- CI/CD pipeline (`.github/workflows/ci.yml`, TDD §12.1): Pint (lint),
  Larastan/PHPStan (static analysis), the full test suite against SQLite
  on PHP 8.3/8.4, and a production asset build with an enforced ≤100KB
  gzipped JS budget (TDD §9.1).
- `.env.example` updated to the platform's actual required configuration
  (MySQL/MariaDB, database-driven queue/cache as the Hostinger launch
  fallback, Sanctum stateful domains) — no secrets committed.

### Verified against acceptance criteria (TDD §16 / Run 1.1 exit criteria)

- "A logged-in user can be created with a role": `RegistrationTest`
  covers registration → authenticated session → `buyer` role assigned.
- "CI runs green on an empty-but-wired app": `ci.yml` lint, static
  analysis, test, and asset-build jobs all pass locally against this
  commit (11 tests, 27 assertions).
- Authorization-matrix test (TDD §11) exists for the one Policy-protected
  route introduced in this run.
- JS payload budget (TDD §9.1, ≤100KB gzipped): production build measures
  38.86KB gzipped.

### Deferred / flagged for a later run

- Actual Space Grotesk / Inter / JetBrains Mono WOFF2 binaries are not
  in this repository (Design System §2.1 requires self-hosted fonts, not
  a Google Fonts request). `resources/css/app.css` declares the
  `@font-face` rules against `public/fonts/...` paths with the correct
  `font-display: swap` and weight ranges; the licensed OFL font files
  themselves need to be added as a binary asset before shipping — a build
  step, not an architecture decision.
- Larastan could not be installed in this sandbox (an outbound GitHub
  network blip on the dist-package fallback); it is fully declared in
  `composer.json`/`composer.lock`/`phpstan.neon` and will install
  normally in CI or on a developer machine with standard GitHub access.
- No Blade/Livewire UI ships in this run — Run 1.1's scope is the
  application skeleton, RBAC and CI/CD only. The Design System's
  component library is built starting Run 1.4 (Storefront).
- Full sub-admin permission seeding (concrete `admin_roles` rows like
  "Catalogue moderator", "Finance officer") is deferred to the runs that
  introduce the resources those roles govern (module 31 is a live
  behaviour, not a fixed seed list).
