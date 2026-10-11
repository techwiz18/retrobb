#!/bin/sh
# Serve the LIVE board on :8080 with pdo_mysql available (uses retrobb-php image).
# Also ensures Mailpit (dev SMTP sink + inbox UI on :8025) so PHP mail() works locally.
ROOT="$(pwd)"
docker rm -f retrobb 2>/dev/null || true
if ! docker ps --format '{{.Names}}' | grep -q '^retrobb-mailpit$'; then
  docker run -d --rm --name retrobb-mailpit --network retrobb-net -p 8025:8025 -p 1025:1025 axllent/mailpit 2>&1 | tail -1
fi
docker run -d --rm --name retrobb -p 8080:8000 -v "${ROOT}:/app" -w /app --network retrobb-net \
  retrobb-php php -d sendmail_path="/usr/bin/msmtp -t -oem -oi --host=retrobb-mailpit --port=1025 --from=noreply@localhost" -S 0.0.0.0:8000 -t public public/router.php
sleep 2
docker logs retrobb 2>&1 | tail -1
docker exec retrobb php -m | grep -i -E "pdo_mysql|pdo_sqlite"
