#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

for bin in php composer rsync; do
  command -v "$bin" >/dev/null || { echo "Perlu $bin terpasang (PHP 8.2+)."; exit 1; }
done

composer create-project laravel/laravel "$TMP/app" --no-interaction
(
  cd "$TMP/app"
  composer require laravel/sanctum --no-interaction
  php artisan install:api --no-interaction
)

rsync -a --ignore-existing "$TMP/app/" "$ROOT/backend/"

cd "$ROOT/backend"
[ -f .env ] || cp .env.example .env
php artisan key:generate --ansi

echo
echo "Kerangka Laravel siap di backend/."
echo "Selanjutnya: isi DB_PASSWORD di backend/.env, lalu jalankan:"
echo "  cd backend && php artisan migrate --seed && php artisan serve"
