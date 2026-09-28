# 1. RBAC: how sub-admin least-privilege sets are represented

Date: 2026-09-28
Status: Accepted

## Context

TDD §8.1 specifies the fixed roles a user may hold (buyer, seller, shipper,
admin, sub_admin) via `role_assignments`, and TDD §3.6 module 31 specifies
that sub-admins get *named, least-privilege permission sets* — e.g.
"Catalogue moderator" (categories/brands/reviews only), "Finance officer"
(payments/settlements only) — not a fixed enum of sub-admin variants. The
TDD names the tables (`admin_roles`, `permissions`, `role_permissions`) but
does not spell out how a `role_assignments` row references a specific,
named sub-admin permission set. This is exactly the kind of gap the build
directive says to flag rather than silently improvise past.

## Decision

`role_assignments.scope` is a nullable foreign key to `admin_roles.id`,
used only when `role = 'sub_admin'`. For every other role it stays null.

Permission resolution (`App\Models\Concerns\HasRoles::hasPermission()`)
branches on this:

- Fixed roles (buyer/seller/shipper/admin) look up grants in
  `role_permissions`, keyed by the role string itself.
- `sub_admin` assignments look up grants via the `admin_roles` row their
  assignment is scoped to, through `admin_role_permissions`.

This keeps the fixed roles' permissions global and simple (one row per
role/permission pair) while letting the platform define arbitrarily many
named sub-admin permission sets without new enum values, schema changes,
or code changes — creating a new sub-admin role is a data operation
(`admin_roles` + `admin_role_permissions` rows), never a deploy.

## Alternatives considered

- **A single `permissions` table keyed directly off `role_assignments.id`**
  (per-assignment ad-hoc grants). Rejected: this would let two sellers
  with the "Catalogue moderator" title drift to different actual
  permissions over time, which contradicts "named, reusable permission
  set" and makes the admin UI's role list meaningless.
- **Extending the `role` enum with sub-admin variants**
  (`sub_admin_catalogue`, `sub_admin_finance`, ...). Rejected: TDD §12.2
  requires additive, backward-compatible migrations for normal deploys;
  a new sub-admin title would need an enum migration (or worse, a widened
  `varchar`) every time operations wants a new least-privilege role,
  which is precisely the flexibility module 31 is describing.

## Consequences

- A `Gate::before` bypass grants the fixed `admin` role unconditional
  access (TDD: "full admin access requires explicit assignment, itself
  audit-logged" — the assignment is the privileged, audited step; the
  access it grants is intentionally total). `sub_admin` never triggers
  this bypass — its access is exactly what its `admin_roles` row grants,
  enforced per-Policy, per §8.1's least-privilege default.
- Every Policy written in later runs that needs sub-admin-scoped access
  (e.g. `ProductPolicy` for a catalogue moderator) calls
  `$user->hasPermission('catalogue.manage')` rather than checking
  `hasRole('sub_admin')` directly — the role alone never implies a
  specific capability.
