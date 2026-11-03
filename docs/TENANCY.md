# Tenancy

Jottin keeps all tenants in one database, with `tenant_id` on every tenant-owned row. The rule is enforced centrally, so a feature can't forget it.

## How a query stays in its tenant

1. **The tenant is resolved once, from a trusted source:** the signed-in session, or the API credential. Request input can never set it, and it can't change during a request.
2. **Models extend `TenantScopedModel`,** which runs every query through `TenantScopedBuilder`.
3. **The builder adds the scope when SQL is compiled,** and wraps the caller's conditions in parentheses:

   ```sql
   WHERE `jotts`.`tenant_id` = 1 AND (`id` = 5 OR `id` = 6)
   ```

4. **With no tenant, queries throw.** The failure mode is an error, never an unscoped query.

### Why not just add `where('tenant_id', X)` in a base model?

That's the common approach, and it has real holes that the tests here cover:

| Case | With a plain `where()` | Here |
|---|---|---|
| `where('id', 1)->orWhere('id', 2)` | `tenant = 1 AND id = 1 OR id = 2` returns the other tenant's row | Parentheses keep the condition inside the tenant |
| `countAll()` | Ignores `WHERE`, so it counts every tenant's rows | Throws |
| `resetQuery()` | Removes the scope | No effect, because the scope is added at compile time |
| `updateBatch()`, `upsert()`, `truncate()` and similar | Depends on each method | Throws |
| Setting `tenant_id` on insert or update | Can move a row between tenants | Throws; the column is set only by the scope |

## Defense in depth

- **Composite foreign keys:** child tables reference `(tenant_id, id)`, so MySQL itself rejects a link between rows in different tenants, even from buggy code.
- **Architecture tests in CI** fail if a model doesn't extend the base class, code reaches the database directly, or a migrated table lacks a required `tenant_id`. Exceptions must be allowlisted in the test with a reason.

## Known limits

- **Joined tables aren't scoped automatically.** Join on a composite foreign key, so the database guarantees both rows share a tenant.
- **Raw SQL bypasses the builder.** The architecture test catches common forms by pattern, which isn't a proof.
- **MySQL has no row-level security.** Postgres RLS would add a database-enforced layer, but it brings its own traps, such as owner roles that bypass it.

## Choosing an isolation model

| | Shared schema (this app) | Schema per tenant | Database per tenant |
|---|---|---|---|
| **Isolation** | Application-enforced | Stronger namespace boundary | Strongest; separate credentials possible |
| **Cost per tenant** | Lowest | Medium | Highest |
| **Migrations** | One run | One per tenant, and they can drift | One per tenant, and they can drift |
| **Noisy neighbors** | Shared | Mostly shared | Separable |
| **Per-tenant backup or restore** | Hard | Moderate | Easy |
| **Data residency, customer keys** | Hard | Hard | Practical |
| **Cross-tenant reporting** | Easy | Harder | Hardest |
| **Main risk** | One missed filter exposes data | Operating many schemas | Operating many databases |

Shared schema fits Jottin: many small tenants, one product, and low cost per tenant. With regulated enterprise clients, I'd expect pressure toward database per tenant for the largest accounts, possibly as a hybrid. Scoped models make that move easier, because tenant resolution already lives in one place.

## Why no per-tenant subdomains

The tenant comes from the session or API credential, so everyone signs in at one address. Subdomains would help with branding and per-customer SSO, but they aren't what provides isolation, and they would add wildcard DNS and TLS to hosting. The product spec doesn't need them. For a B2B product with company tenants, I'd revisit this, and I'd still verify the session's tenant against the hostname rather than trust the hostname alone.
