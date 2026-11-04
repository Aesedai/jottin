# Interview notes

Talking points tying this build to a senior full stack role on a multi-tenant SaaS platform: multi-tenancy, RBAC, audit, security, CI/CD, CodeIgniter modernization, and AI-assisted development with human oversight.

**Live at https://jottin.app.** See `docs/DEPLOY-AWS.md` for how it's deployed and where this setup would need to grow.

## 1. Multi-tenancy

Isolation should be structurally hard to get wrong, not something each feature remembers. Tenant scope is added when SQL is compiled, because a base-model `where()` leaks through `orWhere()` and `countAll()`, and `resetQuery()` removes it. A join must match on `tenant_id` too, or the builder refuses it. Composite foreign keys add a second layer, and CI fails on unscoped models or tables.

**See:** `compileWhereHaving` in `app/Libraries/Tenancy/TenantScopedBuilder.php` and `docs/TENANCY.md`.

## 2. Roles and access

Permissions are checked on the server on every request, from current data. Route filters enforce the owner role, and removing someone takes effect on their next request. Another tenant's record returns the same 404 as one that doesn't exist.

**See:** the `role:owner` routes in `app/Config/Routes.php`.

## 3. Audit trail

An audit log should be trustworthy and shouldn't become a new data risk. MySQL triggers refuse updates and deletes, and a change and its audit record commit together. Entries hold identifiers, never note text. Someone with high enough database privileges could still drop the triggers, so real tamper evidence would mean shipping the log elsewhere.

## 4. Security

The interesting risks are specific to the feature. Webhooks let users choose where the server sends requests, which is an SSRF risk. So every resolved address must be public, the check repeats at send time, and the connection pins to the checked address to defeat DNS rebinding. Other measures include session rotation, API keys stored only as hashes, encrypted webhook URLs, and a content security policy.

**See:** `docs/WEBHOOKS.md` and `tests/unit/WebhookUrlValidatorTest.php`.

## 5. CI/CD and Git workflow

Small PRs with a pipeline make changes reviewable and safer. The work ran on feature branches with one PR per feature, and CI runs a secret scan, lint, static analysis, tests on real MySQL, and a production image build.

## 6. CodeIgniter 3 to 4 modernization

Migrate gradually, starting with tests and low-coupling pieces: characterization tests first, move logic into services while still on CI3, then run CI4 beside CI3 behind a router, one route group at a time.

**See:** `docs/CI3-TO-CI4.md`.

## 7. AI-assisted development with oversight

AI is fast and sometimes confidently wrong, so the value is in the verification around it. In this build, a screenshot caught a bug that all the tests missed, a validation rule it invented failed in testing, and an API check exposed an incomplete Git history wipe. To test the tests, protections were deliberately broken one at a time, confirming the suite failed each time. A human also changed its direction on hosting, tenant subdomains and API design.

**See:** `docs/AI-ASSIST-NOTES.md`, including what I'd restrict with sensitive data.

## 8. Reliability and integrations

Be honest about failure instead of pretending it doesn't happen. Automatic deliveries use a durable queue that commits with the jott, and no HTTP runs inside a database transaction. A crash mid-send is reported as failed rather than retried, because a retry could send a duplicate.
