# Changelog

All notable changes to BuyAndMarket v2 are documented here, grouped by
build run (see `docs/adr/` for the architectural decisions behind them).

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
