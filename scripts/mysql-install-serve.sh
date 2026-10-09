#!/bin/sh
# Serve RetroBB on :8097 backed by the EMPTY retrobb_fresh database (install test).
ROOT="$(pwd)"
docker rm -f retrobb-install-web 2>/dev/null || true
docker run -d --rm --name retrobb-install-web -p 8097:8000 -v "${ROOT}:/app" -w /app --network retrobb-net \
  -e RETROBB_DB=mysql \
  -e RETROBB_MYSQL_HOST=retrobb-mysql \
  -e RETROBB_MYSQL_PORT=3306 \
  -e RETROBB_MYSQL_DB=retrobb_fresh \
  -e RETROBB_MYSQL_USER=retrobb \
  -e RETROBB_MYSQL_PASS=retrobb \
  retrobb-php php -S 0.0.0.0:8000 -t public public/router.php
sleep 2
docker logs retrobb-install-web 2>&1 | tail -1
