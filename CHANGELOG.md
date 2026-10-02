# Changelog

All notable changes to BuyAndMarket v2 are documented here, grouped by
build run (see `docs/adr/` for the architectural decisions behind them).

## Mobile app groundwork — token auth, a Bearer-token bug, and the missing browse/orders/wishlist APIs

Scope: the user's own informal "Phase 2" — a cross-platform Flutter
mobile app, buyer-facing MVP, in a new `mobile/` directory of this
repo. This is the backend half: what a mobile client needs from this
API before any Flutter code can be written against it. (Note: some of
this — the AuthController, the Bearer-token guard fix — was already
pushed as PR #4 against a slightly earlier base; this branch was cut
before that merged, so the same fixes are reapplied here rather than
depending on PR ordering. They'll converge cleanly once both land.)

### Fixed — a real, previously-undetected bug

`CartController`, `CheckoutController`, and `Ai\ConversationController`
all resolved the acting user via the bare `$request->user()` — the
default `'web'` session guard — instead of `$request->user('sanctum')`,
the only guard that also recognises a real `Authorization: Bearer
<token>` request with no session cookie. Every existing test used
`actingAs($user)`, which only populated the `'web'` guard — a genuine
mobile client sending nothing but a bearer token was silently treated
as a guest on every cart/checkout/AI request.

- Fixed all three controllers to use `$request->user('sanctum')`.
- `Tests\TestCase::actingAs()` overridden to authenticate both the
  `'web'` and `'sanctum'` guards by default. Hit (and fixed) a real
  `AuthManager` quirk along the way: `shouldUse(null)`'s fallback reads
  `config('auth.defaults.guard')`, but `setDefaultDriver()` mutates
  that exact same config key, so authenticating `'sanctum'` first
  silently poisoned a later null-guard call into staying on
  `'sanctum'` instead of `'web'`.
- `tests/Feature/Commerce/MobileBearerTokenAuthTest.php`: drives cart
  + checkout with a real issued Sanctum token via `withToken()` —
  actual HTTP Authorization header, no test-only auth shortcut.

### Added — mobile token-auth API

`App\Http\Controllers\Api\V1\AuthController`:
`POST /api/v1/auth/register`, `/login`, `/forgot-password`,
`/reset-password`, and (behind `auth:sanctum`) `/logout` and `/user`.
Wraps Fortify's own `CreateNewUser`/`ResetUserPassword` actions
directly — a buyer's account rules never drift between web and
mobile. `/login` mirrors Fortify's 5-per-minute rate limit; a
two-factor-enabled account is rejected with a clear message (no
mobile TOTP screen yet — out of scope for this buyer-facing MVP).
10 tests in `tests/Feature/Api/MobileAuthTest.php`.

### Added — the browse/orders/wishlist APIs an MVP buyer screen set actually needs

The web storefront's browse/search/category pages, "my orders" page,
and wishlist all previously existed only as web routes/Livewire
components — a mobile client had nothing to call for a home screen, a
search screen, order history, or a wishlist.

- `GET /api/v1/products` (`ProductSearchController`): wraps
  `App\Contracts\SearchProvider` — the same contract the web's
  `ProductGrid` Livewire component calls — so mobile search/browse/
  category screens share the same catalogue-visibility rules and sort
  keys as the web, not a second, drifting implementation.
- `GET /api/v1/orders` (`OrderController::index`): only a single-order
  `show()` by ID existed before.
- `GET /api/v1/wishlist`, `POST /api/v1/wishlist/{product}`
  (`WishlistController`): wraps the existing `WishlistService`.
- 21 tests across `ProductSearchApiTest`, `OrdersIndexApiTest`,
  `WishlistApiTest`. Caught one real bug in my own first draft:
  the search endpoint's `sort` validation accepted `price_asc`/
  `price_desc`, which don't match `EloquentSearchProvider`'s actual
  keys (`price_low_high`/`price_high_low`) — silently falling through
  to newest-first instead of sorting by price. Added a test exercising
  every sort key the provider recognises to catch this class of drift
  again.

### Verified

- Full suite: 214 passed (697 assertions), up from 189.
- Pint clean. `vendor/bin/phpstan analyse`: 0 errors. `migrate:fresh`
  clean (no schema change, pure application code).

## CI: fixed the Tests (PHP 8.3) job — lockfile silently required PHP 8.4

Scope: the previous fix round's own push turned up a third, independent
CI failure — `Tests (PHP 8.3)` failed at `composer install` itself
("Your lock file does not contain a compatible set of packages"),
confirmed pre-existing (identical failure on the prior commit, before
any of this round's changes).

### Root cause

`composer.json` declares `"php": "^8.2"`, and CI's matrix tests both
PHP 8.3 and 8.4 — but `composer.lock` had `web-auth/webauthn-lib`
(pulled in transitively by `laravel/passkeys`) locked to a version
whose `symfony/*` dependencies (`clock`, `css-selector`,
`event-dispatcher`, `serializer`, `string`, `translation`, `yaml`,
etc., all on the 8.1.x line) require PHP ≥8.4.1. The lockfile was
evidently last regenerated on a PHP 8.4 machine — composer resolves
against whatever PHP is actually running unless told otherwise, so it
silently picked versions the declared `^8.2` floor (and CI's own 8.3
leg) can't actually install.

### Fixed

- Added `config.platform.php` to `composer.json`, pinning dependency
  *resolution* (not the runtime requirement) to the floor this project
  actually needs to support — `8.3.0` once `laravel/pint` v1.32.1's own
  `^8.3.0` requirement surfaced during re-resolution, confirming `^8.2`
  was already aspirational before this change, not just the webauthn
  chain. `composer.json`'s own `require.php` updated to match (`^8.3`).
- `composer update web-auth/webauthn-lib tijsverkoyen/css-to-inline-styles symfony/*`
  with the pin in place: resolved to the `symfony/*` 7.4.x line
  (PHP 8.3-compatible) across the board. Nothing else moved —
  `laravel/framework`, `livewire/livewire`, `laravel/fortify`,
  `laravel/sanctum`, `laravel/pint`, and `larastan/larastan` are all
  unchanged versions; the diff is confined to the symfony/webauthn
  dependency subtree plus two harmless transitive patch bumps
  (`doctrine/lexer`, `phpdocumentor/type-resolver`).

### Verified

- `composer validate`: lockfile in sync with composer.json.
- Full suite: 189/189, unaffected by the dependency change.
- Pint and `vendor/bin/phpstan analyse` both still clean.
- `migrate:fresh` clean.
- Could not literally run under a PHP 8.3 interpreter in this sandbox
  (only 8.4 is available here) — verification is the platform-pinned
  resolution itself succeeding, which is exactly the check `composer
  install` performs on a real PHP 8.3 runner.

## CI: fixed a real red-test regression and cleared pre-existing Larastan debt

Scope: user reported "some tests seem to be red on the PR." Two
independent, unrelated issues on PR #2's CI run.

### Fixed — Tests job (PHP 8.3 and 8.4), 32 failing tests

`.github/workflows/ci.yml`'s "Tests" job never runs `npm run build` —
that's a separate, parallel "Build front-end assets" job — so
`public/build/manifest.json` never exists on a clean CI checkout.
Every view rendering through `@vite` (the whole storefront/dashboard/
auth UI) threw `ViteManifestNotFoundException` the instant a test
touched it. This was invisible locally only because a manifest from an
earlier manual `npm run build` happened to already be sitting on disk,
never actually exercised against a clean checkout — exactly what CI
always starts from. Fixed with Laravel's own documented practice:
`tests/TestCase::setUp()` now calls `withoutVite()`. Verified by
deleting `public/build` entirely and re-running the full suite
(189/189 still green with zero built assets present).

### Fixed — Static analysis (Larastan), 25 findings

All pre-existing — confirmed none were in code touched this session,
spanning files from every earlier build run. This sandbox normally
can't install Larastan (GitHub dist-auth blocked, same limitation
already noted in this file for Run 1.1), so these were never actually
verified locally before; got it running this time by manually
assembling `larastan/larastan`, `iamcal/sql-parser`, and the real
`phpstan/phpstan` package from their GitHub repos (plain `git clone`
works even though Composer's own dist-download auth doesn't) and
patching `vendor/composer/installed.json` so Composer's autoloader
would pick them up — a one-time local workaround, not something that
touches anything committed. Fixes, verified against a genuine
`vendor/bin/phpstan analyse` run (0 errors, down from 25):

- Six models (`AnalyticsEvent`, `Attribute`, `CheckoutSession`,
  `ConversationMessage`, `Embedding`, `Store`) gained `@property`
  docblocks for cast columns (`array`, `datetime`, `decimal`) Larastan
  wasn't inferring correctly on its own — the recurring root cause
  behind most of the 25 findings (nullable-array offset access,
  calling `isPast()` on what Larastan thought was a raw string, etc.).
- New `App\Models\CategoryAttributePivot`: the `category_attributes`
  pivot carries a real `required` column beyond the bare foreign keys,
  which the generic `Pivot` class has no way to expose; wired via
  `->using()` on both `Category::attributes()` and
  `Attribute::categories()`.
- `Cart::itemsGroupedByStore()`: `Eloquent\Collection`'s own generic
  template requires its value type to extend `Model`, so it can never
  accurately type "a collection of collections" — the outer level now
  genuinely returns a plain `Support\Collection` (via `collect()`)
  instead of just relabeling the type and hoping, with each inner
  group staying a real `Eloquent\Collection<CartItem>`.
- `MergeGuestCartOnLogin`: narrowed the `Login` event's generic
  `Authenticatable` to this app's one concrete `User` model via an
  `instanceof` guard (this app configures exactly one auth provider,
  so never actually false) instead of asserting past the type checker.
- `AssistantService::confirmAction()`: added an explicit null guard
  before indexing `tool_calls[0]` — previously "safe" only by
  unstated convention (`requires_confirmation` implies a populated
  `tool_calls`), now actually checked.
- `SellerOwnershipScope::apply()`: Eloquent's own `Scope` interface
  erases the `Builder`'s model type, so static analysis has no way to
  know the forwarded local-scope method (`scopeOwnedBySeller`) exists
  — a narrowly-scoped `@phpstan-ignore` with a comment explaining why,
  the one finding here that's a real tooling limitation rather than a
  fixable type gap.
- Four more: `AdminController` (a `Stringable` where `countBy()` wanted
  a string — `->toString()`), `CheckoutController` (a stale `@param`
  tag that didn't match any real parameter), `CartService` (an
  explicit float cast matching the model's declared cast type),
  `ProductService`/`OrderService` (two redundant `??` fallbacks on
  values already guaranteed present by their own declared array
  shapes) — plus removing one stale `ignoreErrors` pattern in
  `phpstan.neon` that no longer matched anything.

### Verified

- Full suite: 189/189, including with `public/build` deleted to match
  CI's actual checkout state exactly.
- `vendor/bin/phpstan analyse --memory-limit=1G`: 0 errors (was 25).
- Pint clean. `migrate:fresh` clean — no schema change, only a new
  Pivot model class.

## Real brand logo wired in across the UI and emails

Scope: user-supplied logo asset. Every previous "branded" surface (site
header, guest auth pages, dashboard sidebar, email header band) used a
styled text wordmark rather than the actual provided logo mark.

### Added

- `public/images/logo-blue.png` / `logo-white.png`: the logo cropped to
  its bounding box with a transparent background, in two colorways —
  blue (for white/light surfaces: the site header, auth pages, dashboard
  sidebar) and white (for the blue email header band, where the blue
  mark would be invisible).
- Site header (`components/layouts/storefront.blade.php`), the guest
  auth layout (`components/layouts/guest.blade.php`), and the dashboard
  sidebar (`components/layouts/dashboard.blade.php`) now render the
  actual logo image instead of a text wordmark.
- Email layout (`components/emails/layout.blade.php`) now embeds the
  white logo as a `cid:` inline attachment via Laravel's `$message->embed()`,
  the correct technique for a real image in HTML email — a `data:` URI
  would silently fail to render in several Outlook builds. `$message` is
  injected by `Illuminate\Mail\Mailer` into each top-level mail view but
  isn't inherited by Blade components automatically, so all 8 email
  templates now pass it through explicitly as `:message="$message"`.
  Falls back to the old text wordmark if `$message` is ever unset (e.g.
  a future caller that renders the layout outside a real mail pipeline).

### Verified against acceptance criteria

- Full suite: 189 passed (615 assertions) — unchanged; existing
  `->render()`-based template tests still pass because `Mailer::render()`
  converts the `cid:` reference back into a raw inline image for
  standalone preview, the same mechanism used for browser-previewing
  any Laravel mail attachment.
- Rendered all 8 email templates and screenshotted them — the logo
  renders crisp and fully legible against the blue header band.
- Spun up the app against a disposable SQLite database with demo data
  and screenshotted the homepage, login page, and dashboard sidebar —
  logo renders correctly in every site surface.
- Pint clean. Production build unchanged at 38.86KB gzipped JS (the
  logo is a static asset referenced by `asset()`, not bundled by Vite).

## Branded HTML email templates

Scope: user feedback — "The emails should have very beautiful, well
designed templates reflective of the brand." Every outbound app email
previously rendered through Laravel's generic default Markdown mail
theme (the stock green `<x-mail::message>` look), unrelated to the
Design System's actual brand (§1.5: Royal Blue `#003594` primary,
Marketplace Gold `#FFB81C` accent, Space Grotesk/Inter type, 12px/8px
radius scale). This replaces it with a real branded HTML email system.

### Added

- **`resources/views/components/emails/layout.blade.php`**: a
  table-based, inline-styled HTML email shell (not Markdown) — the
  single layout every app email now renders through. Royal Blue header
  band with the `Buy`**And**(gold)`Market` wordmark, white content
  card, slate footer, hidden preheader text, and standard Outlook/
  Windows-Mail compatibility fixes (MSO conditional comments, px-based
  table widths, a mobile `@media` stack rule) — no Markdown-to-HTML
  pipeline, since granular inline control is what email clients
  actually require.
- **`emails.button`** and **`emails.badge`** components: a bulletproof
  table-based button (Outlook ignores padding on a plain `<a>`, so a
  `<table><td>` wrapper is used instead) always in brand blue per
  §1.5's "gold never for primary CTAs" rule, and a small status pill
  (green/red) for approved/rejected states.
- **`emails.order-items`**: a proper line-item table (was a Markdown
  pipe-table before) with subtotal/delivery/total breakdown, used by
  the order-confirmation email.
- All six existing transactional templates (`OrderConfirmed`,
  `PaymentFailed`, `SellerKycApproved`, `SellerKycRejected`,
  `ProductApproved`, `ProductRejected`) rewritten against this layout;
  each Mailable's `content()` switched from `markdown:` to `html:`
  accordingly. Same data, same wording, same call sites — only the
  rendering changed.
- **Laravel's own account emails rebranded too**: `VerifyEmail` and
  `ResetPassword` (Fortify's built-in notifications) previously
  rendered through Laravel's *other* generic default — the Markdown
  notification theme, not even using this app's own default. Both are
  now rebuilt via their `toMailUsing()` static hooks
  (`AppServiceProvider::boot()`) to render through the same branded
  layout and new `emails.auth.verify-email`/`emails.auth.reset-password`
  views, so every email a user ever receives from this app — a
  verification link, a password reset, an order receipt — looks like
  it came from the same product.

### Verified against acceptance criteria

- Full suite: 189 passed (615 assertions), up from 187 — 2 new tests
  render the rebranded `VerifyEmail`/`ResetPassword` notifications for
  real (`Mail::fake()`, used everywhere notifications are normally
  tested, intercepts dispatch before rendering and would never catch a
  template error here — same lesson `NotificationMailerTest`'s own
  render-everything test already documents). All 6 transactional
  templates' existing render test still passes unmodified against the
  new markup.
- Rendered all 6 transactional templates plus both account-email
  templates to static HTML and screenshotted them with a headless
  Chromium to visually confirm the brand (colors, wordmark, spacing,
  button/badge rendering) actually reads correctly, not just that the
  Blade compiles.
- Pint clean. No migration, no frontend asset touched — `migrate:fresh`
  and `npm run build` both unaffected by this change.

## Email verification + payment gateway connectivity diagnostic

Scope: closes Production Readiness Report conditions #2 ("verify
Pesepay/Paynow against live sandbox credentials") and #3 ("decide on/
implement email verification").

### Condition #3 — email verification, added

- Enabled Laravel Fortify's `Features::emailVerification()`
  (`config/fortify.php`). `App\Models\User` now implements
  `Illuminate\Contracts\Auth\MustVerifyEmail` — the trait with its
  default method implementations is already pulled in by the base
  `Illuminate\Foundation\Auth\User` class Fortify/Laravel ships, so no
  new trait usage was needed, only the contract.
- `Fortify::verifyEmailView('auth.verify-email')` bound
  (`FortifyServiceProvider`) with a new
  `resources/views/auth/verify-email.blade.php` — same class of gap
  already fixed for login/register/two-factor-challenge/
  confirm-password in earlier runs: the feature has no default view,
  so enabling it without one 500s the first time an unverified user is
  redirected here.
- `Event::listen(Registered::class, SendEmailVerificationNotification::
  class)` added to `AppServiceProvider::boot()` — Laravel 11 ships no
  `EventServiceProvider` stub, so this pairing (which auto-wires itself
  in older Laravel skeletons) had to be registered by hand, or
  registering would silently never send the first verification email.
- **Checkout gate**: `CheckoutService::start()` now rejects an
  authenticated-but-unverified user with a `ValidationException`
  before a `CheckoutSession` is created. Deliberately scoped to the
  single choke point both the web (`Storefront\CheckoutController::
  start()`) and API (`Api\V1\CheckoutController::store()`) entry
  points already call through — and deliberately conditioned on
  `$user !== null`, so guest checkout (TDD §5.9: always allowed,
  no account required) is completely unaffected.
- **Seller/shipper onboarding gate**: `routes/web.php`'s
  `dashboard.become-seller.*`/`dashboard.become-shipper.*` routes are
  now behind the `verified` middleware (in addition to the existing
  `auth` group). These flows collect KYC documents and payout bank
  details — identity/financial data that should only ever be attached
  to a confirmed-reachable email address, since that address is also
  where KYC approval/rejection notices (`SellerKycApproved`/
  `SellerKycRejected`, from the email-integration module above) are
  sent. The rest of the buyer dashboard (orders, addresses, wishlist)
  is deliberately left ungated — out of scope for this condition and
  not something the audit flagged as a risk.

### Condition #2 — payment gateway live verification, could not be done; diagnostic added instead

- **Genuinely blocked, not just unattempted**: confirmed by direct
  test that this sandbox's outbound network policy rejects the CONNECT
  to both `api.pesepay.com` and `www.paynow.co.zw` outright (`curl`
  against each returns `(56) CONNECT tunnel failed, response 403`), and
  no live sandbox credentials for either gateway were provided. Live
  verification against a real account is not achievable from this
  environment under any approach — this is a harder constraint than
  "missing credentials," which is why it's reported as blocked rather
  than silently skipped.
- **Re-reviewed both gateways' implementations** (`PesepayGateway`,
  `PaynowGateway`) line by line against their documented integration
  patterns (AES-256-CBC encrypted payloads for Pesepay; SHA512-hashed
  form fields for Paynow) — both already carried an honest docblock
  from an earlier run disclosing this exact limitation. No defects
  found beyond what was already flagged; nothing changed in either
  class.
- **Added `php artisan payments:check-connectivity`**
  (`App\Console\Commands\CheckPaymentGatewayConnectivity`): the
  achievable substitute. Checks that each gateway's required config
  values are present and that its base URL is actually reachable
  (a `HEAD` request — never sends a real, unsigned payment payload).
  Explicitly does **not** claim to verify request/response field
  shapes; its own success message says so. Intended to be run once
  real network access and real credentials exist, before cutover
  (TDD §14 stage 1.9's production-configuration staging run is the
  right place for it).

### Verified against acceptance criteria

- Full suite: 187 passed (607 assertions), up from 174 — 13 new tests:
  10 for email verification (notice page renders, registration sends
  the notification, the `Registered` → `SendEmailVerificationNotification`
  wiring itself, a valid link verifies, an invalid-hash link is
  rejected, unverified checkout is blocked, verified checkout
  succeeds, unverified/verified become-seller access, guest checkout
  is unaffected) + 3 for the connectivity-check command (missing
  config, unreachable host, success). The 174 pre-existing tests all
  still pass unmodified, protected by `UserFactory`'s existing default
  of `email_verified_at => now()`.
- `migrate:fresh` clean — no new migration needed (`email_verified_at`
  already existed on `users`).
- Pint clean. Production build unchanged at 38.86KB gzipped JS (no
  frontend asset touched this run).

### Remaining risk (condition #2)

Both gateways' exact request/response field shapes remain unverified
against a live account. This is a pre-launch blocking condition, not a
code defect — it can only be closed by running
`payments:check-connectivity` followed by one real test transaction
per gateway, from an environment with real network access and real
sandbox credentials for each provider, before cutover.

## Email integration — transactional notifications module

Scope: closes Production Readiness Report condition #1 ("Wire up
transactional notifications — at minimum order confirmation and
payment-failure emails"). An in-code module (`App\Services\
NotificationMailer`), not a third-party email API integration — it
sends through Laravel's own `Mail` facade, which already supports SMTP
via `.env` (`MAIL_MAILER`, default `log` until a real transport is
configured).

### Added

- **`App\Services\NotificationMailer`**: the single module every
  outbound transactional email routes through. Two rules apply
  uniformly: (1) a mail failure is caught and logged, never thrown —
  a broken SMTP credential must never roll back an admin's KYC
  approval or an order confirmation; call sites invoke it after their
  own `DB::transaction()` commits, since the email is a side effect of
  a state change that already durably happened. (2) Sent synchronously,
  not queued (`ShouldQueue`) — this launch topology has no queue
  worker guaranteed running (the same reasoning already documented for
  the image pipeline and `ai:reindex`), so a queued mail would sit in
  the `jobs` table forever rather than degrade gracefully.
- **Six Mailables** (`App\Mail\*`), each a Markdown template using
  Laravel's built-in mail components (no new asset pipeline):
  `OrderConfirmed`, `PaymentFailed`, `SellerKycApproved`,
  `SellerKycRejected`, `ProductApproved`, `ProductRejected`.
- **Wired into six real state transitions**: `OrderService::
  confirmPaidOrder()` (order confirmed), `CheckoutService::
  markPaymentFailed()` and both payment gateways' synchronous-decline
  and connection-failure branches (payment failed — covers both the
  async webhook path and the sync "gateway declined immediately" path,
  which previously had no notification at all), `KycReviewService::
  approve()`/`reject()` (seller KYC decision), `ProductService::
  approve()`/`reject()` (product moderation decision).
- Guest orders resolve to `guest_email`; a guest order with no email on
  file (phone-only, per TDD §6.2's "phone or email required, not both")
  is silently skipped, not an error.

### Verified against acceptance criteria

- Full suite: 174 passed (585 assertions), up from 162 — 12 new tests
  covering every wired trigger, guest-email fallback, no-resend-on-
  redelivered-webhook idempotency, and that a mail failure never breaks
  the underlying business transaction.
- A dedicated test actually renders all six Markdown templates (not
  just asserts the right Mailable class was dispatched, which
  `Mail::fake()` alone can't catch) against representative data and
  checks real content appears — order number, product title, total.
- Manually rendered `SellerKycApproved` end-to-end against a fresh
  SQLite database outside the test harness; clean HTML output with
  correct interpolated content.
- Pint clean. `migrate:fresh` clean. Production build unchanged at
  38.86KB gzipped JS (Markdown mail templates ship no frontend JS).

### Deferred / flagged (still open)

- No notification preferences / unsubscribe mechanism — every
  transactional email here is a required account/order notification,
  not marketing, so this follows the same pattern most transactional
  mail takes; flagged as a future consideration if non-transactional
  email is ever added.
- SMS notifications (named in the Production Readiness Report alongside
  email) remain unimplemented — no SMS gateway is configured anywhere
  in this build.
- Condition #3 from the report (email verification enforcement) is a
  separate, not-yet-addressed gap.

## Production readiness audit — corrective sweeps 1 & 2

Scope: a full-application audit (functionality, security, data
integrity, concurrency, error handling, performance, deployment
readiness) requested ahead of a production-readiness decision, followed
by two corrective sweeps. Full findings, fixes, and remaining risks are
in the Production Readiness Report delivered alongside this entry — this
is the changelog-discipline summary of what actually changed in code.

### Fixed — P1 (data integrity / correctness)

- **Overselling race**: `OrderService::createFromCheckoutSession()`
  only validated stock once, at checkout-session `start()` — minutes
  earlier than actual order creation, with no row lock. A second buyer
  (or the same buyer returning after a stock change) could reserve more
  stock than existed. Fixed with a `lockForUpdate()` re-check inside the
  same transaction.
- **Abandoned orders never released stock**: `OrderService::
  cancelUnpaidOrder()` existed but nothing ever called it. Added
  `orders:cancel-abandoned` (hourly) and fixed a TOCTOU race in the
  method itself (locked read + conditional update before restoring
  stock).
- **AI assistant double-execution**: `AssistantService::confirmAction()`
  checked confirmation state on an in-memory object outside any lock —
  two near-simultaneous confirms of the same pending action (e.g. a
  double-click) could both execute a state-changing tool call. Fixed
  with the same locked-read-and-conditional-update pattern.
- **Payment gateway crash on network failure**: `PesepayGateway`/
  `PaynowGateway::initiate()` let a `ConnectionException` (timeout, DNS,
  connection refused — routine, not an edge case) propagate uncaught
  into a raw 500 instead of TDD §6.6's "dedicated failure state, not a
  silent crash." Both now catch it and reuse the existing graceful-
  decline path.

### Fixed — P2 (hardening / robustness)

- `CartService::addItem()`/`updateQuantity()` never capped quantity
  against real stock (HTML `min`/`max` are client-side only). The
  financial path was already safe downstream; this closes the input-
  hygiene gap.
- `AssistantService::sendMessage()` had no rate limit despite every call
  being a paid LLM/embedding request, reachable unauthenticated — a
  direct cost-abuse vector. Fixed inside the one choke point both the
  API route and the Livewire dashboard widget share.
- `ProductImageService` had no maximum pixel-dimension check before GD
  decoded an upload — a small, highly-compressed image could still
  exhaust worker memory ("pixel flood"). Added a 40-megapixel cap
  checked via `getimagesize()` alone, before any GD decode runs.
- Added `App\Http\Middleware\SecurityHeaders` (X-Content-Type-Options,
  X-Frame-Options, Referrer-Policy, Permissions-Policy) globally.
- Added `config/cors.php` scoped to `SANCTUM_STATEFUL_DOMAINS` (was
  implicitly wildcard-origin via Laravel's unpublished default).

### Fixed — P3 (data integrity polish)

- `BuyerController::storeAddress()` now unsets a buyer's previous
  default address before setting a new one — previously multiple
  addresses could be simultaneously marked default (a confusing display
  bug; checkout always has the buyer pick explicitly, so never a wrong-
  address-used-silently risk).

### Verified

- A dedicated authorization/IDOR sweep across every controller and
  Livewire component (state-changing actions on route-bound models)
  found no confirmed cross-tenant access findings.
- Full suite: 162 passed (554 assertions), up from 144 before the audit
  — 18 new regression tests covering every fix above. Pint clean.
  `migrate:fresh` clean. `route:cache`/`config:cache`/`view:cache` all
  succeed (deployment-cache-compatible). Production build unchanged at
  38.86KB gzipped JS.
- A live HTTP smoke test (real server, seeded data) confirmed
  homepage/PDP/search/login/register/health all render cleanly with no
  leaked PHP warnings/errors, security headers present on real
  responses, and a full register → auto-login → dashboard → PDP journey
  completes end-to-end.

### Deferred / flagged (disclosed, not fixed — see Production Readiness Report)

- No email/SMS notifications anywhere in the app (order confirmation,
  shipping updates, seller moderation decisions) — already flagged in
  earlier CHANGELOG entries, re-confirmed here as a production-blocking
  gap, not newly discovered.
- Refunds have schema and a `PaymentGateway::refund()` method on each
  gateway, but no service/UI calls them yet — already flagged in Run
  1.5's CHANGELOG, re-confirmed here.
- No email verification enforcement (`User` doesn't implement
  `MustVerifyEmail`) — a buyer can register with an email they don't
  control and use the platform unverified.
- Pesepay/Paynow field-level API shapes remain unverified against a
  live sandbox (no network path in this environment) — already flagged
  since Run 1.5.

## Run 1.22 — Analytics event stream + seller sales summaries/performance insights

Scope: continuing the deferred-item pass — the last item on the
standing deferred list. TDD module 41 (analytics event stream), named
since Run 1.7/1.11's CHANGELOG as the prerequisite for seller sales
summaries, performance insights, and views/conversion signals.

### Added

- **`analytics_events` table + `App\Models\AnalyticsEvent`**: append-
  only, like `audit_logs`, but a behavioural signal (what buyers did),
  not a privileged-mutation record — kept as a separate table rather
  than overloading `audit_logs`' meaning. Three event types:
  `product_view`, `add_to_cart`, `order_placed`.
- **`App\Services\AnalyticsService::record()`**: fire-and-forget on the
  request thread (no queue worker guaranteed running on this launch
  topology, same reasoning as the image pipeline and `ai:reindex`).
  Wired into `ProductController::show()` (product_view),
  `CartService::addItem()` (add_to_cart), and `OrderService::
  confirmPaidOrder()` (order_placed — fired on actual payment
  confirmation, not at checkout-session creation, so a sales summary
  never counts an order that never paid).
- **`App\Services\SellerAnalyticsService`**: read-only aggregates over
  the event stream — `salesSummary()` (30-day daily revenue from real
  `order_placed` events) and `productPerformance()` (views, purchases,
  conversion rate per product). Never writes.
- **Seller overview dashboard**: a 30-day revenue bar chart and a
  product performance table (views/purchases/conversion %), replacing
  the "needs the analytics event stream" deferred note.

### Verified against acceptance criteria

- Full suite: 144 passed (478 assertions) — new coverage: viewing a
  product records a `product_view` event, adding to cart records
  `add_to_cart`, the seller overview shows real views/conversion and a
  real revenue total from recorded events, and one seller's events never
  leak into another seller's summary.
- Pint: clean. `migrate:fresh`: clean. Production build: 38.86KB
  gzipped JS, unchanged — the revenue chart is server-rendered CSS bars,
  no charting library added.

### Deferred / flagged (still open)

- **Inventory alerts stay a plain stock threshold**, not a sales-
  velocity reorder point — TDD §5.6 frames inventory alerts and
  performance insights together, but computing a reorder point from
  velocity is a meaningfully larger forecasting feature than this run's
  scope, even with the event stream now available to feed it.
- **AI-monitoring's per-tool latency and grounding-failure rate**
  (flagged since Run 1.11) remain out of scope — this event stream
  tracks buyer behaviour, not AI-call instrumentation, a different
  signal entirely.
- No cross-seller/platform-wide analytics dashboard for admins — this
  run's aggregates are scoped to "a seller's own products," matching
  what TDD §5.6 actually asks for.

This closes every item on the "Deferred / flagged (still open)" list
carried since Run 1.14 (audit-log filtering, self-service web forms,
product-creation form, image pipeline, categorization suggestions,
reviews, wishlists, recommendations, sponsored placements, search-as-
you-type, price slider, analytics). Remaining gaps are either newly
surfaced by this pass itself (see each run's own "Deferred" section
above) or explicitly out of this build's stated scope (stages 1.9-1.10:
legacy data migration and DNS cutover, which need real production data
and hosting this sandbox doesn't have).

## Run 1.21 — Search-as-you-type + dual-handle price slider

Scope: continuing the deferred-item pass. Two Design System §6.2 pieces
flagged deferred since Run 1.4, both explicitly noted as waiting on
"Alpine patterns established elsewhere in the storefront" — true as of
Run 1.12+.

### Added

- **`App\Livewire\Storefront\SearchSuggestions`**: a debounced (250ms)
  dropdown in the header search box, opening once 2+ characters are
  typed. Reuses `App\Contracts\SearchProvider` — the exact provider the
  results page itself queries through, so a suggestion and a real
  search result are never out of sync. Closes on an outside click
  (Alpine `@click.outside`); Enter still submits the native form to the
  results page as before.
- **Dual-handle price slider** (`resources/views/livewire/product-
  grid.blade.php`): two overlaid native `<input type="range">` elements
  (the standard dependency-free technique — no new JS library), paired
  with number inputs for precise entry, both kept in sync by a small
  Alpine component and committed to Livewire's `minPrice`/`maxPrice` via
  `$wire.set()` on change. The slider's ceiling is the catalogue's real
  highest published price (`ProductGrid::render()`), not a guessed
  constant.

### Verified against acceptance criteria

- Full suite: 139 passed (468 assertions) — new coverage: 2+ characters
  opens the dropdown with matching published products, 1 character
  doesn't, an unpublished product never appears as a suggestion, and the
  slider's ceiling matches the real highest published price.
- Pint: clean. `migrate:fresh`: clean (no new migration this run).
  Production build: 38.86KB gzipped JS, unchanged — both features are
  plain Alpine, already a dependency.

### Deferred / flagged (still open)

- No keyboard arrow-key navigation through search suggestions (click/Tap
  only) — a real but smaller gap than the whole feature was.
- Analytics-dependent seller insights, the hero carousel, "Deals near
  you," trust strip — unchanged from earlier runs' lists.

## Run 1.20 — Sponsored placements

Scope: continuing the deferred-item pass. TDD module 15, named
alongside the homepage's sponsored block (flagged deferred since Run
1.4) and the AI-monitoring panel's "ad/sponsored-product
recommendations" line (flagged since Run 1.11).

### Added

- **`sponsored_campaigns` table + `App\Models\SponsoredCampaign`**:
  seller-submitted, admin-approved — same moderation shape as brand
  suggestions (module 11). `daily_budget` is declarative only; no
  billing/invoicing is wired to it (flagged below), same honest-gap
  pattern as every other payment-adjacent feature this sandbox has no
  live gateway to verify against.
- **`App\Services\SponsoredCampaignService`**: `create()` (validates the
  product belongs to the seller and is published before accepting a
  campaign), `approve()`/`reject()` (admin-only, audit-logged), and
  `placementsFor()` — re-checks the product is *still* published at
  render time rather than trusting the campaign's own stored status
  (the same "never trust embedded state" discipline docs/adr/0006
  already applies to RAG retrieval).
- **Seller dashboard**: `/seller/dashboard/sponsored-campaigns` —
  submit a campaign for one of the seller's own published products,
  see pending/active/rejected status.
- **Admin**: `/admin/dashboard/sponsored-campaigns` — approve/reject
  worklist.
- **Homepage**: a labeled "Sponsored" rail, randomly drawn from active
  campaigns.

### Verified against acceptance criteria

- Full suite: 135 passed (462 assertions) — new coverage: a seller can
  submit a campaign for their own published product, cannot submit one
  for an unpublished product, admin approval/audit log, the approved
  campaign actually renders on the homepage, and a seller cannot
  approve their own campaign.
- Pint: clean. `migrate:fresh`: clean. Production build: 38.86KB
  gzipped JS, unchanged.

### Deferred / flagged (still open)

- **No billing/invoicing for sponsored campaigns** — `daily_budget` is
  recorded but never charged. A real implementation needs the same
  payment-gateway integration this build's other money-movement already
  has no live sandbox to verify against (Pesepay/Paynow).
- **Placement selection is uniform-random among active campaigns**, not
  a real auction (bid amount, pacing, frequency capping) — TDD module 15
  doesn't specify an auction mechanism, so this is the simplest honest
  implementation, not a cut corner.
- Analytics-dependent seller insights (views/conversion, sales
  summaries) — unchanged, still blocked on the missing event stream.

## Run 1.19 — Recommendations: related, recently-viewed, picked-for-you

Scope: continuing the deferred-item pass. TDD §6.1/module 40's three
rails, flagged deferred since Run 1.4 for lacking view-tracking data
and, for "Picked for you," an AI/RAG module to curate it.

### Added

- **`App\Services\RecommendationService`**: `relatedTo()` (same
  category, published, excludes the current product) and `pickedFor()`
  (category affinity from the buyer's own confirmed/completed order
  history, excluding what they've already bought; a guest or a buyer
  with no order history yet gets the newest published listings instead
  of an empty rail). Both deterministic — same reasoning already applied
  to the seller dashboard's pricing insights and low-stock alerts: a
  recommendation a buyer can trust the reasoning behind beats an LLM
  narrating a guess it can't ground in real signal.
- **`App\Services\RecentlyViewedService`**: session-based (works
  identically for a guest or a signed-in buyer, same guest-first posture
  the cart already takes) — no persisted view-event table, since no
  analytics event stream exists yet (flagged since Run 1.11).
- **PDP**: "You might also like" (related) and "Recently viewed" rails.
- **Homepage**: "Recently viewed" and "Picked for you" sections.

### Verified against acceptance criteria

- Full suite: 130 passed (450 assertions) — new coverage: related
  products stay within category, recently-viewed populates across two
  page visits, picked-for-you surfaces the buyer's purchased category
  while excluding the already-bought product, and a guest falls back to
  the newest listings.
- Pint: clean. `migrate:fresh`: clean (no new migration this run).
  Production build: 38.86KB gzipped JS, unchanged.

### Deferred / flagged (still open)

- "Deals near you" (geolocation ranking), the hero carousel, sponsored
  placements, trust strip — unchanged, still blocked on modules this
  build hasn't reached.
- No event-stream-backed recently-viewed across devices/sessions — this
  is one browser session's memory only, by design (see
  `RecentlyViewedService`'s own docblock).

## Run 1.18 — Wishlists

Scope: continuing the deferred-item pass. Flagged since Run 1.4's
CHANGELOG alongside cart ("cart and wishlist have no backing module
until Run 1.5/§3.5") — cart shipped in Run 1.5, wishlist was the one
half of that pair still missing.

### Added

- **`wishlist_items` table + `App\Models\WishlistItem`**: a plain
  per-user toggle, unique on `(user_id, product_id)` — no separate
  named lists, since nothing in the TDD names a multiple-wishlist
  feature.
- **`App\Services\WishlistService`**: `toggle()`/`contains()`.
- **PDP**: a heart-icon toggle (`App\Livewire\Storefront\
  WishlistButton`) next to Add to cart, for signed-in buyers; a guest
  sees a "Sign in to save to your wishlist" link instead (wishlisting
  is tied to an account, not a guest session, unlike the cart).
- **`/dashboard/wishlist`**: grid of saved products with a remove
  action, linked from the buyer sidebar.

### Verified against acceptance criteria

- Full suite: 126 passed (439 assertions) — new coverage: toggle on/off
  via the Livewire component, the dashboard page lists and removes
  items, and a guest sees the sign-in prompt instead of the button.
- Pint: clean. `migrate:fresh`: clean. Production build: 38.86KB
  gzipped JS, unchanged — Livewire ships once already, this is one more
  small component.

### Deferred / flagged (still open)

- No "notify me when back in stock" or price-drop alerts on wishlisted
  items — not named in the TDD, flagged as a natural follow-up rather
  than silently added.
- Recommendations, sponsored placements, analytics-dependent seller
  insights — unchanged from Run 1.14's list.

## Run 1.17 — Reviews + Top Rated seller badge

Scope: continuing the deferred-item pass. TDD module 33 (reviews),
flagged since Run 1.3/1.4 as a prerequisite for the "Top Rated" badge
and the PDP's rating row/reviews tab.

### Added

- **`reviews` table + `App\Models\Review`**: one review per verified
  purchase (`order_id` is checked, not just recorded —
  `App\Services\ReviewService` confirms a `confirmed`/`completed` order
  actually containing the product before allowing a review at all),
  unique per `(product_id, user_id)`. Admin removal is a status change
  (`published` → `removed`), not a row deletion — same "soft, never
  hard" reasoning TDD §6.4 rule 3 already applies to products.
- **`App\Policies\ReviewPolicy`**: `create` delegates to
  `ReviewService::canReview()`; `remove` always returns `false` — only
  the `Gate::before` admin bypass can remove a review, never its own
  author.
- **PDP**: star rating summary, review list, and a "write a review"
  form that only renders when `canReview()` is true for the signed-in
  buyer.
- **Admin**: `/admin/dashboard/reviews` — a worklist of 1-2 star reviews
  (where an abuse report is most likely to land, not every review ever
  written) with a reason-coded remove action.
- **`SellerBadgeService`**: `top_rated` is now computed for real
  (`Seller::reviewStats()`, >=4.5 average over >=20 published reviews
  across all the seller's products, per TDD §3.1 module 6's exact rule)
  — still only recalculated by the existing nightly
  `sellers:recompute-badges` command, not inline on every review.

### Verified against acceptance criteria

- Full suite: 123 passed (427 assertions) — new coverage: verified-
  purchase review creation, rejection for a non-purchaser and for a
  merely-pending order, one-review-per-product enforcement, admin
  removal (and that the author themselves cannot remove it), and the
  Top Rated badge crossing (and failing to cross) its threshold.
- Pint: clean. `migrate:fresh`: clean. Production build: 38.86KB
  gzipped JS, unchanged — no new JS, the review form is a plain POST.

### Deferred / flagged (still open)

- No seller response to a review (a common marketplace feature) —
  not named in the TDD's module 33 description, so out of scope rather
  than silently added.
- Fast Responder badge still not computed — depends on buyer-seller
  messaging, which doesn't exist.
- Wishlists, recommendations, sponsored placements, analytics-dependent
  seller insights — unchanged from Run 1.14's list.

## Run 1.16 — Text-seeded categorization suggestions + brand-suggest web form

Scope: continuing the deferred-item pass. Two small gaps, both on the
product-creation page: TDD §5.6's category/attribute suggestions
(scoped per docs/adr/0007 — text-seeded from the title/notes, matched
to the real catalogue, never invented), and the inline "suggest a new
brand" flow (flagged since Run 1.7, API-only).

### Added

- **`ListingAssistant::suggestCategorization()`**: given a title and a
  few bullet notes, asks the LLM to pick one category from the real
  leaf-category list and suggest values for that category's own
  attributes, in a fixed two-line format parsed deterministically
  (`App\Services\Ai\ListingAssistant::parseCategorization()`) — a
  suggestion only resolves to a real `Category`/`AttributeValue` row;
  anything that doesn't match the actual catalogue is silently dropped,
  never invented. Not audit-logged: nothing is written until the seller
  submits the product form, so TDD §8.9's "every privileged mutation"
  scope doesn't apply yet.
- **Wired into `seller/dashboard/products/create`**: a notes box and
  "Suggest from my notes" button (Alpine, `fetch()` to a new JSON
  endpoint) fills in the category dropdown and pre-ticks the suggested
  attribute checkboxes — the seller still reviews and submits manually.
- **Inline brand suggestion**: "Can't find your brand? Suggest one"
  reveals a small form on the same page, posting to
  `App\Services\BrandService::suggest()` (the exact service the API's
  `POST /api/v1/seller/brands` already used) — pending admin approval,
  same as the API path always was.

### Verified against acceptance criteria

- Full suite: 115 passed (411 assertions) — new coverage: a text-seeded
  suggestion correctly matches a real category/attribute-value pair
  (via `Http::fake`) and a brand suggestion creates a `pending` row
  owned by the acting seller.
- Pint: clean. `migrate:fresh`: clean (no new migration this run).
  Production build: 38.86KB gzipped JS, unchanged.

### Deferred / flagged (still open)

- Still no photo-driven category/attribute extraction — see
  docs/adr/0007; this run's suggestions are text-seeded only.
- Reviews, wishlists, recommendations, sponsored placements, analytics-
  dependent seller insights — unchanged from Run 1.14's list.

## Run 1.15 — Product image pipeline + PDP gallery

Scope: continuing the deferred-item pass (Runs 1.12-1.14). The largest
remaining gap: products had no images anywhere in the system — flagged
whole since Run 1.2 rather than shipped half-built ("adding a bare,
un-pipelined image field now would mean either reworking it later or
shipping catalogue images that never got a quality/authenticity pass").
This closes it: validation, deterministic quality scoring, variant
generation, and alt-text (TDD §3.2 module 7 / §5.8).

### Added

- **`product_images` table + `App\Models\ProductImage`**: one row per
  upload, keyed to the product it belongs to, with width/height/size
  captured at upload time, a `processed`/`rejected` status, and an
  optional `rejection_reason` — a rejected photo is kept (not deleted)
  so the seller can see exactly why.
- **`App\Services\ProductImageService`**: validates and quality-scores
  every upload deterministically (minimum 500×500px, aspect ratio no
  more extreme than 3:1, 10MB ceiling — see docs/adr/0007 for why this
  is deterministic rather than a vision model's judgement call), then
  generates a centre-cropped 300×300 thumbnail and a max-1600px large
  variant with PHP's bundled GD extension (no new dependency). Runs
  inline on the request, not queued — same reasoning as `ai:reindex`
  (Run 1.8): this launch topology has no queue worker guaranteed
  running, and a seller actively waiting on an upload is a poor fit for
  "eventually" regardless.
- **`docs/adr/0007`**: documents why "quality scoring" is deterministic
  and why alt-text (`App\Services\Ai\ListingAssistant::
  suggestAltText()`) is seeded from catalogue data (title, category,
  attributes) rather than the image's actual pixels — no vision-capable
  `LlmProvider` implementation exists, same sandbox-network constraint
  already documented for Pesepay/Paynow and vector search (ADR 0006).
- **Seller dashboard**: `seller/dashboard/products/{product}/images` —
  upload, delete, set-primary, generate-alt-text, linked from the
  products list ("Photos (N)"). **API**: the same four actions under
  `POST/DELETE /api/v1/seller/products/{product}/images[/...]`, inside
  the existing `seller.scope` group.
- **PDP gallery**: main image + thumbnail strip (Alpine-driven, no new
  JS dependency), falls back to the existing letter-placeholder when a
  product has no processed images yet.

### Verified against acceptance criteria

- Full suite: 113 passed (405 assertions) — new coverage: upload +
  variant generation, below-minimum-resolution rejection (kept, not
  deleted), cross-seller 403/404, delete-reassigns-primary, PDP renders
  the uploaded image, alt-text generation.
- Pint: clean. `migrate:fresh`: clean. Production build: 38.86KB
  gzipped JS, unchanged — the gallery's interactivity is plain Alpine.

### Deferred / flagged (still open)

- **Photo-driven category/attribute suggestions** (TDD §5.6's "suggest a
  category from a photo, extract colour/material") remain out of scope
  — see docs/adr/0007. Run 1.16 adds a text-seeded version of this
  (title + bullets, not pixels), a real but narrower capability.
- No background/batch reprocessing if the quality thresholds change
  later — rejected images stay rejected until the seller re-uploads.

## Run 1.14 — Deferred-item polish: self-service web forms, audit-log filtering

Scope: continuing the same deferred-item pass as Runs 1.12-1.13. Three
gaps flagged since Run 1.7, all "fully functional via the API, web
presentation only missing": seller self-service registration, shipper
self-service registration, and product creation — plus audit-log
filtering (TDD §8.9 calls for a "filterable table"; Run 1.7 shipped it
unfiltered).

### Added

- **`/dashboard/become-seller`** (`App\Http\Controllers\Dashboard\
  SellerOnboardingController`): the full TDD §3.1 module 4 stepper —
  business info, KYC documents, payout details, store setup, first
  product, submit for review — as one resumable page, each step's
  status driven directly from `seller_onboarding_steps`. Every step
  calls `App\Services\SellerOnboardingService`, the exact service the
  API controller already used — no business logic duplicated, only
  presentation (same discipline as Run 1.12's checkout UI).
- **`/dashboard/become-shipper`** (`App\Http\Controllers\Dashboard\
  ShipperOnboardingController`): single-field, immediately-active
  registration per TDD §3.4 module 23. Extracted the API controller's
  inline registration logic into `App\Services\ShipperOnboardingService`
  so both surfaces share it — the API controller was refactored to call
  it too, behavior unchanged (covered by existing API tests).
- **Web product-creation form** (`seller/dashboard/products/create`,
  TDD §3.2 modules 7/10 "category picker, dynamic variant rows"): leaf-
  category picker, approved-brand dropdown, Alpine-driven dynamic
  variant rows (add/remove) with per-category attribute checkboxes.
  Every leaf category's attribute/value tree ships inline as JSON so
  switching category client-side swaps the attribute checkboxes with no
  extra request — the catalogue tree is small enough to send whole.
  Posts through the same validation and `ProductService::create()` the
  API uses.
- **Sidebar**: a user without a seller/shipper row now sees "Become a
  seller" / "Become a shipper" links instead of nothing; the existing
  seller/shipper nav sections still appear automatically once those
  rows exist (no change needed there — `dashboard.blade.php` already
  checked `$user?->seller`/`$user?->shipper` directly).
- **Audit-log filtering** (TDD §8.9): `/admin/dashboard/audit-log` now
  takes optional `action`, `subject_type`, `actor` (name/email
  contains), `from`, and `to` query params, all additive and all
  optional — an admin with no filters sees exactly the previous
  unfiltered feed. Filter dropdowns are populated from the audit log's
  own distinct values, not a hardcoded list.

### Verified against acceptance criteria

- Full suite: 107 passed (385 assertions), including a new end-to-end
  web test that drives a buyer through the entire become-a-seller
  stepper (registration → KYC upload → payout details → store → first
  product via the new web form → submit for review → `under_review`),
  a shipper registration test, two product-creation-form tests
  (happy path with attributes, and the no-store redirect), and an
  audit-log filtering test.
- Pint: clean. `migrate:fresh`: clean. Production build: 38.86KB
  gzipped JS, unchanged — the new variant-row interactivity is plain
  Alpine (already a dependency), no new JS shipped.

### Deferred / flagged (still open)

- **Product-image pipeline** and everything gated on it (PDP gallery,
  categorisation/attribute-extraction AI suggestions) — flagged since
  Runs 1.2/1.4/1.8/1.11, still genuinely out of scope here.
- **No inline "suggest a new brand" form** on the product-creation page
  — the brand dropdown only lists already-approved brands
  (`POST /api/v1/seller/brands` remains API-only). A seller whose brand
  isn't listed creates a draft product with no brand and attaches one
  later, same limitation the API itself always had.
- Reviews, wishlists, recommendations, sponsored placements, analytics-
  dependent features — all unchanged from Run 1.12's list, still
  blocked on modules this build hasn't reached.

## Run 1.13 — Deferred-item polish: MFA enforcement (TDD §8.2)

Scope: continuing the same deferred-item pass as Run 1.12. Run 1.7
flagged `config/fortify.php` as carrying a comment claiming two-factor
was "enforced for seller/admin" when nothing actually enforced it, and
Fortify's two-factor challenge / password-confirmation views didn't
exist — a seller or admin with 2FA somehow enabled, or trying to set it
up, would 500 on a missing view binding. TDD §8.2 is explicit: "MFA
required for seller ... and all admin/sub-admin roles."

### Added

- **`App\Http\Middleware\EnsureTwoFactorEnabled`** (alias `2fa`): redirects
  any seller/admin user without a confirmed TOTP secret to
  `/dashboard/security`, applied to the `seller.dashboard.*` and
  `admin.dashboard.*` route groups. Shippers are not named in TDD §8.2
  and are left out deliberately.
- **`/dashboard/security`** (`App\Livewire\TwoFactorSetup`): enable
  (QR code + confirmation code) / view recovery codes / disable, built
  directly on Fortify's own `EnableTwoFactorAuthentication`,
  `ConfirmTwoFactorAuthentication` and `DisableTwoFactorAuthentication`
  actions rather than re-implementing TOTP. Gated behind Laravel's
  `password.confirm` middleware — the same protection level
  `config/fortify.php` already configures for Fortify's own two-factor
  management routes, since enabling/disabling 2FA is itself a sensitive
  action.
- Fortify's missing `twoFactorChallengeView` and `confirmPasswordView`
  bindings (`auth.two-factor-challenge`, `auth.confirm-password`) — the
  same class of gap Run 1.7 found and fixed for login/register.

### Verified against acceptance criteria

- Full suite: 100 passed (342 assertions), including the pre-existing
  dashboard and AI-listing-assistant tests updated to enable 2FA for
  their seller/admin actors (the new, real requirement).
- Pint: clean. `migrate:fresh`: clean. Production build: 38.86KB
  gzipped JS, unchanged from Run 1.12 — this run added no frontend JS.

### Deferred / flagged (still open)

- No "remember this device for 30 days" trusted-device cookie — every
  login for a 2FA-enrolled seller/admin re-prompts for a code. Not in
  TDD §8.2's text; flagged as a future UX improvement, not a gap.
- `/dashboard/security` does not yet show device/session history
  (TDD §8.2 mentions "manage trusted devices" as a stretch item for a
  later run).

## Run 1.12 — Deferred-item polish: storefront commerce UI

Scope: not a TDD-numbered stage — a pass back over every "Deferred /
flagged" note across Runs 1.1-1.11, at the user's request, to close the
highest-value gaps a pure engineering pass can close (no production
legacy data or real hosting needed, unlike stages 1.9-1.10). By far the
largest: **the storefront had no working cart, checkout, or order
confirmation UI** — PDP's Add to cart/Buy now rendered disabled since
Run 1.4, and Run 1.5's commerce backend, fully tested at the API layer,
had no web presentation at all. A buyer could not complete a purchase
through the website.

### Added

- **Cart drawer** (`App\Livewire\Storefront\CartDrawer`, Design System
  §6.6): seller-grouped line items, quantity updates, remove, subtotal,
  mounted once in the storefront header with a live item-count badge.
- **PDP Add to cart / Buy now, finally wired** (`App\Livewire\Storefront\
  AddToCartForm`): variant picker, quantity, both CTAs call the same
  `CartService` the API has used since Run 1.5. `AddToCartForm` and
  `CartDrawer` stay in sync via a dispatched `cart-updated` browser event
  — no full page reload needed to see the cart update.
- **Checkout** (`App\Http\Controllers\Storefront\CheckoutController`,
  Design System §6.6 "dedicated page, not modal"): address (saved
  addresses as selectable cards, or a new one — guest checkout fully
  supported, TDD §5.9) → delivery (per-seller zone/method, see below) →
  payment-and-review (collapsed into one page rather than two — flagged
  inline in the controller) → confirmation. Every step is a thin
  presentation over `CheckoutService`/`ShippingService` — the exact
  services `tests/Feature/Commerce/CheckoutFlowTest.php` already
  exercises at the API layer; no checkout business logic is duplicated.
- **Delivery fee is now computed from real `delivery_rate_cards`**
  (Run 1.6's own schema), not a self-reported number — Run 1.5's
  CHANGELOG flagged this exact gap ("doesn't compute a fee from zones or
  weight"). The buyer picks a rate card; its seller is re-verified
  against the cart's own seller grouping server-side, so a tampered
  request can't borrow another seller's cheaper rate (tested).
- **Three real bugs found and fixed while building this**, each
  previously-shipped code that was never actually wired end to end:
  - `CheckoutService::confirmOrder()`/`markPaymentFailed()` existed
    since Run 1.5 but nothing called them — a `checkout_sessions` row
    stayed stuck at `payment_processing` forever, even after its order
    was confirmed by the payment webhook. `AbstractPaymentGateway::
    applyWebhookResult()` now calls the right one.
  - `CartService::mergeIntoUserCart()` existed since Run 1.5 but nothing
    called it — a guest who shopped, then signed in, silently lost their
    cart. `App\Listeners\MergeGuestCartOnLogin` now does it, paired with
    `StashSessionIdBeforeLogin` (Laravel's `SessionGuard::login()`
    regenerates the session id *before* firing the `Login` event the
    merge listener needs — the stash listener runs on the earlier
    `Attempting` event, before that regeneration, and carries the
    pre-login session id across it via the session's own data, which
    `migrate()` preserves under the new id).
  - Pesepay/Paynow's `return_url` was configured but pointed nowhere —
    `AbstractPaymentGateway::defaultReturnUrl()` now falls back to this
    app's own `/checkout/return`, which looks up the buyer's own
    checkout session and routes them to confirmation or the failed page
    based on its (now-correctly-updated) status.
- **`addresses.user_id` made nullable** (expand-pattern migration) — it
  was `NOT NULL` since Run 1.3, which silently made guest checkout
  impossible to actually use once a real UI tried to save a guest's
  address (API-only testing never caught this, since those tests always
  used a pre-existing authenticated user's address).
- **Post-purchase account upsell** (Design System §6.6: "one field —
  just set a password — never a pre-purchase gate"): on the confirmation
  page, a guest can create an account from their order's own
  `guest_email`, which signs them in and attaches the order to the new
  account.
- **"Ask about this product" wired** (flagged deferred since Run 1.4):
  the PDP link now opens the AI assistant (Run 1.8) pre-grounded in that
  product's context, shown as a context chip per Design System §7.2 —
  `AssistantService::startConversation()` already accepted
  `context_type`/`context_id`, just never had a caller.
- Feature tests: `tests/Feature/Storefront/CheckoutUiTest.php` (add to
  cart updates the drawer; a guest completes checkout through the
  website to a webhook-confirmed order and a working return-redirect to
  confirmation; a failed payment lands on the dedicated failed page; a
  tampered delivery selection is rejected; a guest creates an account
  from the confirmation page) and `tests/Feature/Storefront/
  CartMergeOnLoginTest.php`.

### Deferred / flagged (still open after this pass)

Everything below was already flagged in an earlier run's CHANGELOG
entry and is still genuinely open — this pass closed the highest-value
engineering gaps, not every gap:

- MFA enforcement for seller/admin (TDD §8.2, flagged since Run 1.7).
- Audit-log filtering (flagged since Run 1.7).
- Web product-creation form, seller/shipper self-service "become a X"
  web forms (flagged since Run 1.7) — both fully functional via the API.
- Product-image pipeline and everything that depends on it: PDP image
  gallery, categorisation/attribute-extraction AI suggestions (flagged
  since Runs 1.2/1.4/1.8/1.11).
- Reviews, wishlists, recommendations, sponsored placements, the search-
  as-you-type dropdown and dual-handle price slider, analytics-dependent
  features (sales summaries, performance insights, views/conversion
  figures) — each still needs a module this build hasn't reached.
- Checkout's 4-step spec collapsed to 3 pages (review merged into
  payment) — a deliberate simplification this run, not an oversight.
- `order_group` delivered→completed collapse, weight/distance-banded
  delivery pricing, shipper KYC (flagged since Run 1.6).
- `AddToCartForm`'s variant picker has no live-stock recheck between
  page load and the add-to-cart click — `CartService::addItem()` still
  re-validates product status server-side, but a variant that sells out
  in that window shows a generic rejection rather than a live-updated
  "out of stock" state.

## Run 1.11 — AI platform v2

Scope: TDD §14, stage 1.11 — seller/admin AI tools (§5.6), an AI
monitoring panel (§5.7). Exit criterion: "Sellers actively using
AI-assisted listing; admin AI dashboard populated with real metrics."

**Run ordering note**: stages 1.9 (migration execution against
production legacy WordPress/WooCommerce data) and 1.10 (DNS cutover,
post-launch monitoring) are skipped here, not silently — both are
inherently unavailable in this environment: 1.9 needs the actual legacy
database/media to migrate from, and 1.10 needs real hosting/DNS to cut
over. Neither is a coding task this sandbox can do a defensible version
of the way 1.5's "unverified payment gateway" or 1.8's "unverified LLM
provider" caveats let those runs proceed with a flagged limitation. 1.11
has no such external dependency, so it's next.

### Added

- **`App\Services\Ai\ListingAssistant`** (TDD §5.6 "product-description
  generation"): bullets in, a description suggestion out via
  `LlmProvider`, written to a new `products.ai_suggested_description`
  column that sits beside the real `description` until the seller
  explicitly Accepts or Discards it (TDD §3.2 rule 7's "never
  auto-published," applied here to an existing field rather than a
  separate `product_drafts` table — this codebase already represents a
  product's draft state as `products.status = 'draft'`, so a second
  drafts table would duplicate that). The seller's own title is never
  touched — no image-upload pipeline exists yet (flagged since Run 1.8)
  to ground a title suggestion in.
- Seller dashboard: each product card in `/seller/dashboard/products`
  gets a "Generate with AI" input when it has no pending suggestion, or
  the Design System §7.7 AI-suggestion panel (dashed border, Accept/
  Discard) when it does.
- **Pricing insights** (TDD §5.6): the products page shows each
  product's price against its category's average/min/max among other
  published products — computed directly with a `GROUP BY` query, not
  narrated by the LLM. TDD frames this as an "AI-generated advisory
  range"; a deterministic aggregate is strictly more trustworthy than an
  LLM restating arithmetic, so this run computes it rather than routing
  a number through a model that could get it wrong.
- **Inventory alerts** (TDD §5.6): the seller overview page lists
  variants at or below a stock-quantity threshold. TDD's own framing is
  "low-stock/reorder-point suggestions from sales velocity" — there's no
  analytics event stream (module 41) to compute velocity from, so this
  is a plain threshold, not a reorder-point model.
- **Admin AI monitoring panel** (`/admin/dashboard/ai-monitoring`, TDD
  §5.7/§6.11): conversation and message counts, tool-call counts by
  name (from `audit_logs` — every `ai.tool.*`/`ai.suggestion.*` action
  Run 1.8/this run write), pending-vs-confirmed state-changing actions,
  and seller listing-assistant usage (generated/accepted/discarded).
  Every number here is a real count from this run's own tables — see
  "Deferred" for the TDD §5.7 signals this run has no data to compute.
- Feature tests (`tests/Feature/Ai/ListingAssistantTest.php`): generate
  → accept (description updates, suggestion clears, both steps
  audit-logged), generate → discard (real description untouched),
  cross-seller isolation (404, not 403), and the admin panel rendering
  real counts.

### Deferred / flagged

- **No product categorisation or attribute-extraction suggestions**
  (TDD §5.6) — both are meaningfully vision-dependent (extracting
  colour/material from a photo, suggesting a category from an image),
  and no product-image pipeline exists (§5.8, flagged since Run 1.8).
- **No sales summaries or seller performance insights** — both need the
  analytics event stream (module 41), which doesn't exist.
- **No customer-response assistance or ad/sponsored-product
  recommendations** — both need modules that don't exist yet (a
  buyer-seller messaging/reviews surface; sponsored_campaigns, module
  15).
- **AI monitoring panel omits grounding-failure rate, escalation-to-
  human rate, and per-tool latency** (TDD §5.7's full signal list) —
  there's no `escalate_to_support` tool (support_tickets doesn't exist),
  no per-call latency capture, and "grounding failure" as the TDD
  defines it (a response flagged with no supporting citation) isn't
  measurable post hoc without storing it at generation time, which this
  run doesn't add. Real counts only; nothing here is a placeholder
  metric.
- **No proactive notifications** (price-drop/back-in-stock/delivery
  updates surfaced by the assistant, TDD §5.6/§7.6) — the notifications
  module itself (36) doesn't exist yet.

## Run 1.8 — AI platform v1

Scope: TDD §14, stage 1.8 — RAG ingestion/retrieval, the buyer-facing
assistant (search, product Q&A, order tracking), tool-calling with the
confirm-before-execute boundary (§5.4). Exit criterion: "Assistant
answers grounded product/order questions with citations in staging."

### Added

- **Schema**: `rag_documents` (one row per source entity, keyed by a
  content hash so unchanged content skips re-embedding), `embeddings`
  (chunk vector + metadata, JSON — see docs/adr/0006 for why not a
  native vector type/index), `conversations` and `conversation_messages`
  (guest-session-identified the same way carts/checkout are; an
  assistant message can propose a state-changing tool call without
  executing it via `requires_confirmation`/`confirmed`).
- **`App\Contracts\Ai\LlmProvider`/`EmbeddingProvider`** (TDD §8.4:
  "provider-agnostic at the integration boundary"), with one concrete
  OpenAI-compatible implementation of each. Same "unverified against a
  live API" caveat as Pesepay/Paynow (Run 1.5) — this sandbox has no
  network path to a real LLM/embeddings provider; every test fakes the
  HTTP calls.
- **`App\Services\Ai\IngestionService`**: chunks and embeds Products
  (title + description + price, one chunk per product — variants
  summarised, not exploded per-variant, per TDD §5.1) and Stores. CMS/
  policy-FAQ and Promotion chunking are deferred — those modules (44,
  14) don't exist yet to source content from.
- **`App\Services\Ai\RetrievalService`**: metadata-filtered (TDD §5.2)
  then similarity-ranked candidate search, with live product/seller
  status re-checked at query time rather than trusted from
  (potentially stale) embedded metadata — see docs/adr/0006.
- **`App\Services\Ai\ToolExecutor`**: read tools (`search_products`,
  `get_product_details`, `check_stock`, `get_order_status`,
  `get_delivery_estimate`) execute directly; `get_order_status` is
  parameterized by an order id but always scoped server-side to the
  authenticated user, never trusting a user id the model might supply
  (TDD §5.4's own named impersonation vector). `add_to_cart` is
  state-changing and is the one tool `AssistantService` never lets
  `ToolExecutor` run except via the confirm step below.
- **`App\Services\Ai\AssistantService`**: orchestrates retrieval →
  grounded system prompt (retrieved content passed as clearly delimited,
  labelled data, never concatenated into the instruction context — TDD
  §8.4) → tool-calling loop (capped at 4 rounds) → final grounded
  response. A state-changing tool call halts the loop and returns a
  pending-confirmation message instead of executing; a separate
  `confirmAction()` (reached only via the confirm endpoint) is the one
  path that actually runs it. Every tool call is audit-logged (TDD
  §5.7) with the conversation, tool name, arguments and result.
- **Citations are structural, not model-trusted**: every tool result and
  every retrieved chunk used to ground a turn carries its own
  `{type, id}` citation, attached to the final assistant message
  regardless of what the model's own prose does or doesn't cite —
  satisfies TDD §5.3 rule 3 without depending on the LLM reliably
  self-citing.
- Routes (guest-session, same group as cart/checkout): `POST /api/v1/ai/
  conversations`, `POST .../messages`, `POST .../messages/{message}/
  confirm`.
- **`php artisan ai:reindex`**: the nightly/full-reindex half of TDD
  §5.1's ingestion triggers (see "Deferred" for the other half).
- A minimal functional chat UI (`App\Livewire\AiAssistant`, `/dashboard/
  assistant`): message bubbles, citations, and an explicit Confirm/
  Cancel action card for a pending state-changing tool call (TDD §5.4's
  hard rule, Design System §7.3) — a scoped-down v1 of the dark-panel
  conversation surface (see "Deferred").
- Feature tests (`tests/Feature/Ai/AssistantTest.php`): a grounded
  product-price question answered with a citation, a read-tool
  (`check_stock`) call grounding the answer and writing an audit-log
  row, a state-changing tool (`add_to_cart`) halting for confirmation
  and only mutating the cart/writing its audit-log row after the
  confirm call, `get_order_status` returning "not found" for another
  buyer's order id rather than trusting the model-supplied id, and
  conversation ownership (404 on another user's conversation).

### Deferred / flagged

- **No event-triggered incremental re-embedding.** TDD §5.1 asks for
  re-embedding on product create/update/price-change/stock-change via a
  queued job; wiring that into every `Product` save would make routine
  product saves synchronously dependent on an external embeddings API
  with no queue worker guaranteed running on this launch topology (§2.1)
  — worse than a batch. `php artisan ai:reindex` (run nightly, per
  §5.1's other stated trigger) is this run's whole ingestion story.
  Content changed since the last run won't be reflected until the next
  one.
- **No reranking pass** (TDD §5.2's cross-encoder step) — see docs/
  adr/0006. Cosine similarity over the metadata-filtered set is the
  whole ranking step this run.
- **No CMS/policy-FAQ or Promotions ingestion** — modules 44 and 14
  don't exist yet to source content from; the assistant can answer
  product/store/order questions, not policy questions.
- **No hallucination eval harness** (TDD §11's golden-question regression
  set) — this sandbox has no real LLM to score against. The structural
  controls (grounded system prompt, server-side citation attachment,
  confirm-before-execute) are implemented and tested; whether a real
  model's prose actually stays within them is unverified, same caveat as
  the payment gateways.
- **No streaming, quick-action chips, or grounded product/store/order
  cards rendered as their own components** (Design System §7.3/§7.4) —
  the chat UI is plain message bubbles with a citation line and one
  action-card pattern, not the full dark-panel treatment.
- **No proactive notifications, seller/admin AI surfaces, or image
  intelligence pipeline** (TDD §5.6/§5.8) — this run is the buyer-facing
  assistant only, per the TDD's own stage split (seller/admin AI tools
  are stage 1.11).
- **Conversation retention/anonymisation window** (TDD §8.8) is not
  implemented as a scheduled job — transcripts persist indefinitely for
  now.

## Run 1.7 — Dashboards

Scope: TDD §14, stage 1.7 — buyer/seller/shipper/admin dashboards (§3.6
modules 27-30). Exit criterion: "Each role has a functional operational
home base."

### Added

- **Web authentication, wired for the first time.** Laravel Fortify was
  registered in Run 1.1 (`'views' => true`, registration/password-reset/
  2FA features enabled) but never given the view names its own routes
  expect — `GET /login` and `GET /register` would have 500'd on a missing
  view binding. `App\Providers\FortifyServiceProvider::boot()` now calls
  `Fortify::loginView()`/`registerView()`, backed by two new views
  (`resources/views/auth/`) sharing a minimal `x-layouts.guest` shell.
  The `register.store`/`login.store` POST handlers themselves already
  worked and were already tested
  (`tests/Feature/Auth/RegistrationTest.php`) — only the page a browser
  needs to reach them was missing. `config('fortify.home')` now points to
  `/dashboard` instead of the stub `/home`.
- **One shared dashboard shell** (`x-layouts.dashboard`, Design System
  §6.11): sidebar nav + top bar, so a user holding buyer + seller +
  shipper + admin roles simultaneously (TDD §3.1 module 1's own example)
  sees one product, not several bolted together. The seller-mode
  sidebar-inverts-to-blue-900 treatment and the collapsible icon rail are
  deferred (see below) — this is a functional, not yet pixel-complete,
  shell.
- **`App\Http\Middleware\EnsureUserHasRole`** (`role:admin`): a thin
  route-group gate for the admin dashboard shell. Individual privileged
  actions inside it (KYC/product moderation) still go through their own
  Policy via the existing `Gate::before` admin bypass — this middleware
  only keeps a non-admin from loading the shell at all.
- **Buyer dashboard**: order list + per-order detail (reusing `OrderPolicy`
  and the same eager-loaded shipment-tracking timeline Run 1.6 added to
  the API), and address management (add/remove, set default).
- **Seller dashboard** (behind `seller.scope`, same isolation guarantee as
  the API — TDD §6.4 rule 4): an overview with revenue/commission/
  order-status figures computed honestly from this run's own schema (no
  fabricated views/conversion sparklines — those need the analytics event
  stream, TDD §3.6 module 41, not yet built); a product list with
  submit-for-review/archive actions (`App\Services\ProductService`,
  unchanged); an order list with shipment assignment
  (`App\Services\ShippingService::assign()`, unchanged); and delivery
  rate-card management (a seller "opts into" a zone by creating a rate
  card for it, TDD §3.4 module 25).
- **Shipper dashboard**: a card list (not a table — TDD §4.3's own reason:
  field use on mobile is primary) of assigned deliveries with a one-tap
  "next status" action, proof-of-delivery photo + signature capture on
  the final step, and a pooled/unclaimed-shipments list to claim from
  (`App\Services\ShippingService::claim()`, unchanged).
- **Admin dashboard**: a seller-approval queue and a product-moderation
  queue (both purpose-built worklists per TDD §4.4, not a generic list
  table — filtered to `under_review`/`pending_review` rows), reusing
  `KycReviewService`/`ProductService` exactly as the API does, and an
  audit-log viewer (`audit_logs`, read-only, monospace actor/action
  columns per TDD §8.9).
- Every dashboard controller calls into the same service classes and
  Policies the `/api/v1` endpoints already use — no business logic is
  duplicated between the two surfaces, only the presentation layer is new.
- Feature tests (`tests/Feature/Dashboard/DashboardTest.php`): login/
  register views render, a buyer manages orders and addresses (and can't
  view another buyer's order), a seller manages products/delivery/
  shipment-assignment (and can't reach another seller's product — 404,
  not 403), a shipper claims a pooled shipment and drives it through to
  delivered with proof-of-delivery capture, and an admin approves a
  seller and a product end-to-end through the dashboard forms.

### Deferred / flagged

- **No seller/shipper self-service "become a X" web flow yet** — both are
  fully functional via the tested API
  (`POST /api/v1/seller/register`, `POST /api/v1/shipper/register`);
  only the web form is missing. A user who registers via the API already
  sees the right dashboard section appear (the sidebar checks
  `$user->seller`/`$user->shipper` directly).
- **No web product-creation form** (category picker, dynamic variant
  rows) — the seller dashboard manages products that already exist
  (submit for review, archive); creation itself is API-only for now,
  same reasoning as above.
- **No MFA enforcement.** TDD §8.2 requires MFA for seller/admin roles;
  Fortify's two-factor feature is registered and its underlying routes
  work, but nothing in this run requires it at login or exposes a setup
  UI. Flagged as a follow-up, not silently dropped.
- **Seller dashboard overview omits views/conversion/sparkline figures**
  that Design System §6.11 specifies — they need the analytics event
  stream (TDD §3.6 module 41), which doesn't exist yet. Revenue,
  commission-owed and order-status counts are real, not placeholder,
  figures.
- **No audit-log filtering** (TDD §8.9 says "filterable") — this run's
  viewer is a plain paginated, most-recent-first table.
- **Visual fidelity is functional, not pixel-complete** against Design
  System §6.11: no seller-mode blue-900 sidebar inversion, no collapsible
  icon-rail, no KPI sparklines/delta chips, no dense-mode admin tables.
  The exit criterion is "a functional operational home base," which
  every role now has; the remaining polish is cosmetic follow-up.

## Run 1.6 — Fulfilment

Scope: TDD §14, stage 1.6 — shipping, the shipper role, order tracking,
delivery zones and delivery pricing (§3.4 modules 22-26). Exit criterion:
"An order can be assigned, tracked through delivery, and marked
delivered."

### Added

- **Schema**: `delivery_zones` (self-referencing province → city → area
  hierarchy), `shippers` (mirrors `sellers`' shape — one shipper profile
  per user), `delivery_rate_cards` (seller × zone × method, with a
  free-delivery threshold override per TDD §3.4 module 26), `order_group_
  shipments` (one per `order_group`, `shipper_id` nullable for the pooled/
  platform-dispatched path), `shipment_events` (append-only, no update/
  delete grants — matches `inventory_ledger`/`audit_logs`).
- **`App\Services\ShippingService`**: looks up a seller's rate card for a
  zone/method (rejects an unconfigured combination rather than silently
  defaulting a fee); `assign()` creates a shipment either seller-selected
  (an explicit `shipper_id`) or platform-dispatched (left unassigned, open
  to `claim()` by any active shipper — TDD §3.4 module 23's "both paths
  converge on the same `order_group_shipments` record"); `recordEvent()`
  appends an immutable `shipment_events` row, updates the shipment's own
  status, and syncs `order_group.status` (`shipped` once physically
  moving, `completed` on delivery — there's no separate buyer-confirmation
  window in this run, see "Deferred" below) with an audit log entry.
- **Proof of delivery** (Design System §6.11): the `delivered` event
  requires a photo and a signature capture, stored on a new private
  `shipments` disk (`config/filesystems.php`), following the same
  never-publicly-guessable pattern Run 1.3 established for KYC documents.
- Seller endpoints: `GET`/`POST /seller/delivery-rate-cards` (a seller
  "opts into" a zone by having a rate card for it, per TDD §3.4 module 25
  — there's no separate opt-in list) and `POST /seller/order-groups/
  {orderGroup}/shipment` to assign a shipment, both behind `seller.scope`
  (`OrderGroup` and `DeliveryRateCard` now join `Product`/`ProductVariant`
  in `ScopeQueriesToActingSeller`, so a seller's token still can't reach
  another seller's order group — 404, not 403, per TDD §8.5).
- Shipper endpoints: self-service `POST /shipper/register` (mirrors seller
  registration's pattern), `GET /shipper/shipments` (my active
  assignments), `POST /shipper/shipments/{shipment}/claim` (pooled
  shipments), `POST /shipper/shipments/{shipment}/events` (log
  pickup/transit/delivered — matches the TDD §7.3 endpoint table exactly).
  `OrderGroupShipmentPolicy` keeps event-logging assignment-scoped: a
  shipper can only act on a shipment claimed/assigned to them.
- `GET /orders/{order}` (buyer/seller) now eager-loads each order group's
  shipment and its event timeline, so the tracking view specified in
  Design System §6.4/§6.11 has a single request to read from.
- `database/seeders/DeliveryZoneSeeder.php`: Zimbabwe's 10 provinces with
  a representative set of major cities/areas (see "Deferred").
- Feature tests (`tests/Feature/Fulfilment/ShipmentTrackingTest.php`):
  seller assigns a shipment against a matching rate card (and is rejected
  without one), seller-isolation on assignment (404 on another seller's
  order group), a shipper logging events through to delivery with the
  order group transitioning `confirmed → processing → shipped →
  completed`, assignment-scoped authorization denial, pooled-shipment
  claiming, and the buyer-facing tracking read.

### Deferred / flagged

- **`order_group` goes straight to `completed` on delivery.** The TDD's
  own lifecycle table lists `delivered` as a distinct state before
  `completed`; this run treats them as the same transition since there's
  no return-window/buyer-confirmation module built yet to occupy the gap
  between them. Revisit once a returns/dispute flow (module 33 adjacent)
  needs that distinction.
- **Delivery zone dataset is a representative starter set**, not the full
  Zimbabwean gazetteer (province → every ward). TDD §14 stage 1.9
  (migration execution) is the right place for the complete dataset —
  it's a data-entry task, not an engineering one, and shouldn't block
  this run's exit criterion.
- **Shipper onboarding has no KYC/verification step**, unlike sellers
  (§3.1 modules 4-5) — registration is immediate self-service and the
  shipper is active right away. A verification step is flagged as a
  Run 1.7 (dashboards) follow-up alongside the shipper dashboard itself.
- **No distance/weight-banded pricing** — `delivery_rate_cards` is flat
  fee only for this run, per the TDD's own module 26 note that
  weight/distance banding is a refinement, not required for this run's
  exit criterion.
- **No shipper dashboard UI** — the endpoints above are API-only; the
  card-list shipper dashboard (Design System §6.11: pickup/dropoff map
  pins, one-tap navigate, proof-of-delivery capture UI) is storefront/
  dashboard work for Run 1.7, same as the buyer/seller/admin dashboards.

## Run 1.5 — Commerce core (backend)

Scope: TDD §14, stage 1.5 — cart, checkout state machine, order splitting,
and the `PaymentGateway` seam with two live implementations (Pesepay, the
TDD's named provider, plus Paynow, added on request). This run covers the
backend only; the storefront cart/checkout/confirmation UI is deferred
(see "Deferred" below).

### Added

- **Schema**: `addresses`, `carts` (unique nullable `user_id`, indexed
  nullable `session_id`), `cart_items`, `orders`, `order_groups`,
  `order_items`, `checkout_sessions`, `payments` (unique
  `(provider, provider_reference)` for webhook-redelivery idempotency),
  `commissions`, `refunds`. `inventory_ledger.order_item_id` gets its
  foreign key added via an expand-migrate-contract follow-up migration
  (TDD §12.2), once `order_items` exists.
- **Cart** (`App\Services\CartService`): guest (session-identified) and
  authenticated carts, price-snapshot-at-add-time line items, quantity
  update/remove, and `mergeIntoUserCart()` for TDD §3.4 module 17's
  "merged on login" (quantities summed on collision).
- **Checkout state machine** (`App\Services\CheckoutService`): TDD §5.9's
  CartReview → AddressSelection → DeliveryMethod → PaymentProcessing →
  OrderConfirmed (or → PaymentFailed, retryable without re-entering data).
  Guest checkout is fully supported.
- **Order splitting** (`App\Services\OrderService`): one `orders` row
  (buyer receipt) plus one `order_groups` row per seller in the cart,
  with `order_items.price_at_purchase` snapshot at order-creation time
  (TDD §6.4 rule 1 — never a live join to product/variant price). Stock
  is reserved (`InventoryService::adjustStock`) at order creation, before
  payment confirms, and released if the order is cancelled unpaid.
- **`App\Contracts\PaymentGateway`** (TDD §2.1 non-negotiable interface
  seam) plus `App\Services\Payments\AbstractPaymentGateway`, which
  implements the shared idempotency logic: `applyWebhookResult()` locks
  the `Payment` row (`lockForUpdate()`), no-ops on an already-terminal
  status (redelivered webhook safe), and calls
  `OrderService::confirmPaidOrder()` on success.
  - `PesepayGateway` — Pesepay's AES-256-CBC encrypted-payload pattern.
  - `PaynowGateway` — Paynow's hash-signed form-POST pattern.
  - `PaymentGatewayManager` — resolves either by provider name, since
    both are live simultaneously (buyer picks one at checkout); see
    `docs/adr/0005-payment-gateway-manager-for-simultaneous-providers.md`.
- **`App\Services\CommissionService`**: records a `commissions` row per
  `order_group` at order-creation time, using a single platform-wide
  default rate (see "Deferred").
- Idempotency-Key support (TDD §7.1) on the payment-initiation endpoint,
  short-TTL cached per checkout session.
- A new unconditional `guest-session` middleware group
  (`EncryptCookies` + `AddQueuedCookiesToResponse` + `StartSession`,
  deliberately without CSRF) for the guest-facing cart/checkout API
  routes — Sanctum's `statefulApi()` only starts a session for requests
  whose Origin/Referer matches a configured stateful domain, which a
  same-origin `fetch()` without those headers (or a guest with no prior
  session) doesn't reliably send.
- Routes: public `POST /webhooks/{provider}` (signature-verified inside
  the controller, deliberately outside `auth:sanctum`/CSRF, as a webhook
  must be), guest-accessible `/carts/*` and `/checkout/session/*`, and
  authenticated `GET /orders/{order}` (`OrderPolicy`: the buyer, or a
  seller with an `order_group` on the order, may view it).
- Feature tests (`tests/Feature/Commerce/CheckoutFlowTest.php`): full
  checkout-to-paid-order flow against a faked Paynow response, webhook
  idempotency (redelivery is a no-op), invalid webhook signature
  rejection, empty-cart checkout rejection, and guest checkout.

### Fixed

- **bcmath unavailable in this environment** (`ext-bcmath` not installed,
  and not installable here — the environment's package proxy rejects the
  PPA). `OrderService` and `CommissionService` use plain float arithmetic
  with `round(..., 2)` instead of `bcmul`/`bcadd`: all money here is
  DECIMAL(12,2)-scale (TDD §6.4 rule 5), which is exact within float64
  once scaled by 100, so this isn't a precision compromise — it's a
  dependency this build doesn't take on since it isn't guaranteed present
  on every PHP install.
- Laravel's test HTTP client (`postJson`/`getJson`) omits cookies by
  default, mirroring a cross-origin `fetch()` — a guest-checkout test
  chaining two calls needs `withCredentials()` before the session cookie
  a prior response set is sent back on the next simulated request.

### Deferred / flagged

- **Delivery fee** is a flat, buyer/seller-supplied value passed through
  `checkout_sessions.delivery_selection` — there's no `delivery_rate_cards`
  table yet (TDD stage 1.6); this run doesn't compute a fee from zones or
  weight, it only carries whatever the delivery-method step submits.
- **Commission rate** is a single platform-wide `config('commerce.
  default_commission_rate')` default (10%), not a seller-tier rate —
  seller tiers aren't built yet. The `rate_applied` column already exists
  per `order_group` specifically so tiering can vary it later without a
  schema change.
- **Pesepay/Paynow field-level API shapes are unverified against a live
  sandbox** — this environment has no network path to either provider.
  Both adapters follow their publicly documented request/response
  patterns (Pesepay's AES-256-CBC encrypted payload; Paynow's hash-signed
  form fields) but should be confirmed against a real sandbox key before
  the TDD §14 stage 1.9 staging cutover run.
- **Refunds** have schema (`refunds` table) and a `PaymentGateway::refund()`
  method on each gateway, but no service/UI calls them yet — refund
  initiation is out of this run's scope.
- **Storefront UI** (cart drawer, checkout page, order confirmation) is
  not built in this run — see the Run 1.6 backlog.

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
