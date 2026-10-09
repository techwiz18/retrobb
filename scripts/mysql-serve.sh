#!/bin/sh
# Serve RetroBB on :8092 backed by MySQL (leaves :8080 sqlite board alone).
ROOT="$(pwd)"
docker rm -f retrobb-mysql-web 2>/dev/null || true
docker run -d --rm --name retrobb-mysql-web -p 8092:8000 -v "${ROOT}:/app" -w /app --network retrobb-net \
  -e RETROBB_DB=mysql \
  -e RETROBB_MYSQL_HOST=retrobb-mysql \
  -e RETROBB_MYSQL_PORT=3306 \
  -e RETROBB_MYSQL_DB=retrobb \
  -e RETROBB_MYSQL_USER=retrobb \
  -e RETROBB_MYSQL_PASS=retrobb \
  retrobb-php php -S 0.0.0.0:8000 -t public public/router.php
sleep 2
docker logs retrobb-mysql-web 2>&1 | tail -2
