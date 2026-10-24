#!/bin/sh
set -e

# Warm the Symfony prod cache at startup, when DATABASE_URL is available.
# The Docker build warms most of the cache, but some warmers (e.g. EasyAdmin)
# need a real DB connection that isn't present at build time.
php /app/bin/console cache:warmup --env=prod --no-debug 2>&1 || true

exec "$@"
