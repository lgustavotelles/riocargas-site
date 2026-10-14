#!/usr/bin/env bash
# Importa um dump de produção e troca as URLs para o ambiente local.
# Uso: scripts/import-db.sh dumps/riocargas.sql
set -euo pipefail
DUMP="${1:?informe o arquivo .sql em dumps/}"
PORT="${WP_PORT:-8080}"
docker compose run --rm wpcli wp db import "/dumps/$(basename "$DUMP")"
docker compose run --rm wpcli wp search-replace \
  'https://www.riocargas.com.br' "http://localhost:${PORT}" --all-tables --precise --skip-columns=guid
docker compose run --rm wpcli wp search-replace \
  'https://riocargas.com.br' "http://localhost:${PORT}" --all-tables --precise --skip-columns=guid
docker compose run --rm wpcli wp cache flush || true
