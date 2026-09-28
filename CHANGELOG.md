# Changelog

All notable changes to BuyAndMarket v2 are documented here, grouped by
build run (see `docs/adr/` for the architectural decisions behind them).

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
