#!/bin/sh
# Run a PHP CLI command against the MySQL backend (mounts project, sets env).
# Usage: sh scripts/mysql-php.sh php bin/migrate.php --seed
ROOT="$(pwd)"
docker run --rm -v "${ROOT}:/app" -w /app --network retrobb-net \
  -e RETROBB_DB=mysql \
  -e RETROBB_MYSQL_HOST=retrobb-mysql \
  -e RETROBB_MYSQL_PORT=3306 \
  -e RETROBB_MYSQL_DB=retrobb \
  -e RETROBB_MYSQL_USER=retrobb \
  -e RETROBB_MYSQL_PASS=retrobb \
  retrobb-php "$@"
