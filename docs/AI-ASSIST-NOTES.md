# AI-assist notes

A running record of how AI was used to build Jottin, where a human changed its direction, and what governance would apply with sensitive data.

## Where AI helped
- Trimmed the full product spec into a one-day scope and PR plan.
- Scaffolded the CodeIgniter 4 project, Docker setup and CI pipeline.

- Read the framework's query builder source and found that a base-model `where()` would leak through `orWhere()` and `countAll()`, which led to scoping at SQL compile time.

## Where a human corrected or redirected it
- **Hosting:** the AI proposed a dedicated AWS VM. Jordan questioned whether that was necessary, and it turned out to be a convenience rather than a PHP requirement.
- **Tenancy:** the AI recommended keeping per-tenant subdomains. Jordan checked them against the product spec, found they weren't a requirement, and dropped them. Tenants now resolve from the session or API credential.

## Mistakes the AI made, and what caught them
- **Passing check that never ran:** a pipeline hid a crashed `composer check`. It now checks real exit codes.
- **Incomplete history wipe:** the AI force-pushed to remove old history, but GitHub still served those commits by SHA and closed the open PR. Checking through the API exposed it, and the fix was a new repo.
- **Stale local branch:** after the wipe it branched from an outdated local `main`. A failed fast-forward exposed it before any code was written.
- **Overstrict guard:** its first tenant guard refused a call the framework makes internally. The test suite caught that.
- **Invented API:** it used a `max_byte` validation rule that doesn't exist, from memory. The tests caught it.
- **Silently ignored argument:** it passed data to `extend()`, which takes only a layout name, so every page lost its title and the landing page its styling. All tests passed; looking at a screenshot caught it.
- **Overridden Apache setting:** `ServerTokens Prod` from PR 1 was overridden by Debian's own config and leaked the Apache version. Inspecting real response headers caught it.

## Why the tests are trustworthy
Tests written alongside the code can pass without proving much. So the isolation and access protections were deliberately broken one at a time, confirming the suite fails each time. Behavior the test environment can't exercise, such as real sign-out and session storage, was checked over HTTP against the running app.

## Governance I'd apply with sensitive or health data
- To be completed as the build progresses.
