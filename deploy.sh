#!/usr/bin/env bash
# Run on the server (Hostinger SSH) after every git pull/deploy:  bash deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"

echo "→ PHP: $($PHP -r 'echo PHP_VERSION;')"
$PHP -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || { echo "PHP 8.3+ required — set it in hPanel → Advanced → PHP Configuration"; exit 1; }

if [ ! -f .env ]; then
  echo "✗ .env missing. Copy .env.example to .env and fill in DB_* and APP_URL first (see docs/HOSTINGER.md)."
  exit 1
fi

echo "→ Installing PHP dependencies"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --prefer-dist

grep -q '^APP_KEY=base64:' .env || $PHP artisan key:generate --force

echo "→ Database migrations"
$PHP artisan migrate --force

[ -L public/storage ] || $PHP artisan storage:link || echo "! storage:link failed — see docs/HOSTINGER.md (uploaded images)"

echo "→ Caching config/routes/views"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

echo "✓ Deployed. If this is the first deploy, run once: $PHP artisan db:seed --force"
