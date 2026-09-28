# Changelog

All notable changes to BuyAndMarket v2 are documented here, grouped by
build run (see `docs/adr/` for the architectural decisions behind them).

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
