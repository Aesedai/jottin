# AI-assist notes

A running record of how AI was used to build Jottin, where a human changed its direction, and what governance would apply with sensitive data.

## Where AI helped
- Trimmed the full product spec into a one-day scope and PR plan.
- Wrote most of the code, tests and documentation across all the PRs, including the CodeIgniter 4 project, Docker setup and CI pipeline.

- Read the framework's query builder source and found that a base-model `where()` would leak through `orWhere()` and `countAll()`, which led to scoping at SQL compile time.

## Where a human corrected or redirected it
- **Hosting:** the AI proposed a dedicated AWS VM. Jordan questioned whether that was necessary, and it turned out to be a convenience rather than a PHP requirement.
- **Tenancy:** the AI recommended keeping per-tenant subdomains. Jordan checked them against the product spec, found they weren't a requirement, and dropped them. Tenants now resolve from the session or API credential.
- **Inbound API:** Jordan questioned whether the API was more complex than a webhook needed, and pointed to the Webhooker tool. Reading its source showed the endpoint already fit, and led to adding the `message` reply field.
- **Git target:** Jordan confirmed the work goes to GitHub, not the local server.

## Mistakes the AI made, and what caught them
- **Passing check that never ran:** a pipeline hid a crashed `composer check`. It now checks real exit codes.
- **Incomplete history wipe:** the AI force-pushed to remove old history, but GitHub still served those commits by SHA and closed the open PR. Checking through the API exposed it, and the fix was a new repo.
- **Stale local branch:** after the wipe it branched from an outdated local `main`. A failed fast-forward exposed it before any code was written.
- **Overstrict guard:** its first tenant guard refused a call the framework makes internally. The test suite caught that.
- **Invented API:** it used a `max_byte` validation rule that doesn't exist, from memory. The tests caught it.
- **Silently ignored argument:** it passed data to `extend()`, which takes only a layout name, so every page lost its title and the landing page its styling. All tests passed; looking at a screenshot caught it.
- **Default collation trap, caught in design:** MySQL's default collation would have treated `#cafe` and `#café` as one tag, so `tag_key` uses a binary collation, with a test that fails without it.
- **Hidden labels widened the page:** screen-reader text escaped the scrolling collection list and made the feed 720px wide on a 320px screen. All tests passed; measuring the rendered page caught it.
- **Demo flag too permissive:** a switch for the local receiver was only excluded from production, so it also disabled SSRF protection during local test runs. CI didn't set the variable and wouldn't have caught it. It now applies only in development, and a test run with the flag set fails if that regresses.
- **Slow test suite:** rebuilding the schema for every test passed Composer's 5-minute limit, which would have broken CI. The schema now builds once per run.
- **Deploy check that couldn't fail:** the first `deploy.sh` reported success on an HTTPS redirect, because `curl -f` accepts a 3xx. Reading the actual response caught it, and the script now needs a real `ok`.
- **Overridden Apache setting:** `ServerTokens Prod` from PR 1 was overridden by Debian's own config and leaked the Apache version. Inspecting real response headers caught it.

## Checked against production

After deploying, the checks were repeated against the live site, because some risks only exist there. SSRF attempts against AWS instance metadata and internal containers were refused. A spoofed `X-Forwarded-For` didn't bypass the sign-in rate limit, while a second real client kept its own limit. A real webhook was delivered by the worker, and MySQL refused an audit edit. The test accounts were then removed.

## Why the tests are trustworthy
Tests written alongside the code can pass without proving much. So the isolation and access protections were deliberately broken one at a time, confirming the suite fails each time. Behavior the test environment can't exercise, such as real sign-out and session storage, was checked over HTTP against the running app.

## Governance I'd apply with sensitive or health data

**What the AI can see**
- No real client data in prompts, ever: not in logs, tickets, screenshots or database dumps. Use synthetic data, as this project does.
- Only approved tools, under a contract that covers data handling, retention and training use, and a BAA where health data could be involved.
- Secrets stay out of its reach where possible. It works against development credentials, never production ones.

**What the AI can do**
- It works in development environments only, with no direct production access.
- Merging stays under human control for anything touching tenancy, permissions, authentication or data handling. In this build it merged PRs 4–7 after CI passed, on a personal project with synthetic data. On a team with client data, I'd require human approval before every merge.
- Destructive actions need explicit approval. It asked before overwriting Git history here, and that approval only covered that one action.

**How its work gets checked**
- Treat it like a fast new contributor: same review, CI and tests as anyone.
- Verify behavior, not just test results. The checks that caught the most here were screenshots, real HTTP requests and real response headers.
- Test the tests by deliberately breaking protections, because tests written alongside the code can pass without proving much.
- Be especially skeptical of framework details recalled from memory, and check the source.
- Keep the attribution visible in commits, so a reviewer knows what they're looking at.

**Accountability**
- The person who merges owns the result, whoever or whatever wrote it. If I can't explain a change, it doesn't ship.
