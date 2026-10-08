#!/bin/sh
# One-shot: port the live SQLite board into MySQL and switch the live config.
# Safe to re-run (importer skips duplicate rows; config rewrite is idempotent).
ROOT="$(pwd)/retrobb"
SRC="${1:-${ROOT}/storage/backup-walkthrough.sqlite}"
LIVEDB="${2:-retrobb_live}"
docker exec retrobb-mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS \`$LIVEDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON \`$LIVEDB\`.* TO 'retrobb'@'%'; FLUSH PRIVILEGES;" 2>&1 | grep -v "password on the command" || true
docker run --rm -v "${ROOT}:/app" -w /app --network retrobb-net \
  -e RETROBB_DB=mysql \
  -e RETROBB_MYSQL_HOST=retrobb-mysql \
  -e RETROBB_MYSQL_PORT=3306 \
  -e RETROBB_MYSQL_DB="$LIVEDB" \
  -e RETROBB_MYSQL_USER=retrobb \
  -e RETROBB_MYSQL_PASS=retrobb \
  retrobb-php php bin/migrate.php
docker run --rm -v "${ROOT}:/app" -w /app --network retrobb-net \
  -e RETROBB_DB=mysql \
  -e RETROBB_MYSQL_HOST=retrobb-mysql \
  -e RETROBB_MYSQL_PORT=3306 \
  -e RETROBB_MYSQL_DB="$LIVEDB" \
  -e RETROBB_MYSQL_USER=retrobb \
  -e RETROBB_MYSQL_PASS=retrobb \
  retrobb-php php bin/import-sqlite.php "/app/storage/$(basename $SRC)"
