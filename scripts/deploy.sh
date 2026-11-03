#!/usr/bin/env bash
# Runs on the server, from the repo. Pulls a ref (main by default), rebuilds, and migrates.
set -euo pipefail

ref="${1:-main}"
compose=(docker compose -f docker-compose.prod.yml)

git fetch --quiet origin
git checkout --quiet "$ref"
git pull --quiet --ff-only origin "$ref"

"${compose[@]}" build --quiet app
"${compose[@]}" up -d --remove-orphans
"${compose[@]}" exec -T -u www-data app php spark migrate --all

for _ in {1..30}; do
    if "${compose[@]}" exec -T app curl -fsS -o /dev/null -H 'X-Forwarded-Proto: https' http://localhost/health; then
        echo "Deployed $(git rev-parse --short HEAD)"
        exit 0
    fi
    sleep 2
done

echo "The app did not become healthy" >&2
exit 1
