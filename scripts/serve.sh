#!/bin/sh
# Serve the LIVE board on :8080 with pdo_mysql available (uses retrobb-php image).
ROOT="$(pwd)"
docker rm -f retrobb 2>/dev/null || true
docker run -d --rm --name retrobb -p 8080:8000 -v "${ROOT}:/app" -w /app --network retrobb-net \
  retrobb-php php -S 0.0.0.0:8000 -t public public/router.php
sleep 2
docker logs retrobb 2>&1 | tail -1
docker exec retrobb php -m | grep -i -E "pdo_mysql|pdo_sqlite"
