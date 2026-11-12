#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/backend"

for bin in php composer; do
  command -v "$bin" >/dev/null || { echo "Perlu $bin terpasang (PHP 8.3+)."; exit 1; }
done

missing=()
for bin in soffice pdftotext qpdf; do
  command -v "$bin" >/dev/null || missing+=("$bin")
done

composer install --no-interaction

if [ ! -f .env ]; then
  cp .env.example .env
fi
if ! grep -q '^APP_KEY=base64' .env; then
  php artisan key:generate --ansi
fi

echo
echo "Backend siap. Isi DB_USERNAME dan DB_PASSWORD di backend/.env, lalu jalankan:"
echo "  cd backend && php artisan migrate --seed && php artisan serve"

if [ ${#missing[@]} -gt 0 ]; then
  echo
  echo "Peringatan: alat berikut belum terpasang: ${missing[*]}"
  echo "Modul pengesahan soal membutuhkannya (Debian/Ubuntu: sudo apt install libreoffice-writer poppler-utils qpdf)."
fi
