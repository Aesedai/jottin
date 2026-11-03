# AI-assist notes

A running record of how AI was used to build Jottin, where a human changed its direction, and what governance would apply with sensitive data.

## Where AI helped
- Trimmed the full product spec into a one-day scope and PR plan.
- Scaffolded the CodeIgniter 4 project, Docker setup and CI pipeline.

## Where a human corrected or redirected it
- **Hosting:** the AI proposed a dedicated AWS VM. Jordan questioned whether that was necessary, and it turned out to be a convenience rather than a PHP requirement.
- **Tenancy:** the AI recommended keeping per-tenant subdomains. Jordan checked them against the product spec, found they weren't a requirement, and dropped them. Tenants now resolve from the session or API credential.

## Governance I'd apply with sensitive or health data
- To be completed as the build progresses.
