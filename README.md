# Jottin

Multi-tenant note capture: write a short note ("jott"), hashtags file it into collections, and each collection can send its jotts to an automation webhook. Built with PHP 8.3, CodeIgniter 4 and MySQL 8.

## Run it locally

Requires Docker with Compose. PHP and Composer run inside the container.

```bash
cp .env.example .env          # then set real values for both passwords
docker compose up -d --build
docker compose exec -u www-data app composer install
docker compose exec -u www-data app php spark migrate --all
```

Open http://localhost:8080. `GET /health` reports app and database status.

## Checks

```bash
docker compose exec -u www-data app composer check   # lint, static analysis and tests
```

CI runs the same checks on every pull request, plus a secret scan and a production image build.
