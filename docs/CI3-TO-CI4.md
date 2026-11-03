# CodeIgniter 3 vs 4, and migrating incrementally

Jottin is CodeIgniter 4. The CodeIgniter 3 column comes from general knowledge of the framework, and I haven't worked in a production CI3 codebase, so check it against the real code and its version.

## What changes

| Area | CodeIgniter 3 | CodeIgniter 4 | In Jottin |
|---|---|---|---|
| **Install** | Framework folder copied in | Composer dependency | `composer.json` |
| **PHP** | Older versions, and needs care on PHP 8 | Modern PHP (4.7 needs 8.2 or later) | PHP 8.3 |
| **Web root** | `index.php` at the project root by default | `public/` only | `public/` |
| **Classes** | Global names, `$this->load`, `get_instance()` | PSR-4 namespaces, autoloading, Services | `App\Libraries\...` |
| **Routing** | Mostly automatic from class and method names | Explicit, with auto-routing off by default | `app/Config/Routes.php` |
| **Cross-cutting checks** | Hooks, or `MY_Controller` | Filters attached to routes | `AuthFilter`, `RoleFilter`, `ApiAuthFilter` |
| **Models** | Plain classes, usually hand-written queries | `Model` with `allowedFields`, timestamps, validation | `TenantScopedModel` |
| **Results** | `result_array()` and similar | Arrays or Entities | Arrays |
| **Config** | `$config[]` arrays, per-environment folders | Classes with env var overrides, `.env` | `app/Config`, `.env` |
| **CSRF, security headers** | Config options | Filters | `app/Config/Filters.php` |
| **Escaping** | Often manual | `esc()` with a context | All views |
| **CLI** | Controllers run through `index.php` | `spark` and command classes | `deliveries:run` |
| **Testing** | No official feature-test tooling | PHPUnit, `FeatureTestTrait`, `DatabaseTestTrait` | `tests/` |

## Why it isn't an in-place upgrade

CI4 is a rewrite rather than a new version, so nearly every file changes in some way. On a mature product with paying clients, a big-bang rewrite would be the riskiest choice, so I'd migrate gradually.

## How I'd approach it

1. **Write tests against current behavior first.** Characterization tests around the riskiest flows give a safety net before anything moves.
2. **Check for tenant filter patterns.** Look for `or_where` near tenant conditions, since the operator-precedence bug in `docs/TENANCY.md` is easy to write in any query builder.
3. **Move logic out of controllers and into plain services,** while still on CI3. This is the cheapest step, helps immediately, and these classes port easily later.
4. **Add Composer and a CI pipeline to the CI3 app** if they aren't there, plus static analysis at a low level, tightening it over time.
5. **Run CI4 next to CI3 behind a router** (a strangler pattern), sending one route group at a time to the new app, with both on the same database.
6. **Start with low-coupling pieces.** New JSON endpoints and background workers share only the database, with no views or sessions to reconcile.
7. **Share sessions deliberately.** Decide early how a user stays signed in across both apps, because that's usually the hardest seam.
8. **Treat AngularJS as a separate track.** It's long past end of life, so replace screens one at a time rather than coupling that work to the PHP migration.
9. **Set a stopping rule per route group:** moved, covered by tests, and old code deleted. Otherwise two systems can last indefinitely.

## Questions to ask

- Which CodeIgniter and PHP versions are in production, and is PHP's end of life forcing a timeline?
- How is tenant scoping enforced today, and how would you catch a query that missed it?
- What's the test coverage on the tenant and permission paths?
- Is a move to CI4 planned, or is the plan to stay on CI3 and harden it?
- How much of the front end is still AngularJS?
