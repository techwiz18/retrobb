#!/bin/sh
# Boot RetroBB under a given PHP version and run the readonly suite against it.
# Usage: sh scripts/phpver.sh 8.1 8094   (php:8.1-cli on :8094)
VER="$1"
PORT="$2"
ROOT="$(pwd)/retrobb"
docker run -d --rm --name "retrobb-php$VER" -p "${PORT}:8000" -v "${ROOT}:/app" -w /app "php:${VER}-cli" php -S 0.0.0.0:8000 -t public public/router.php
sleep 2
docker run --rm -v "${ROOT}:/app" -w /app "php:${VER}-cli" sh -c 'for f in $(find core models controllers bin public -name "*.php"); do php -l "$f" > /dev/null || exit 1; done && echo LINT-OK'
RETROBB_TEST_BASE="http://localhost:${PORT}" python3 retrobb/scripts/readonly.py 2>&1 | tail -14
docker stop "retrobb-php$VER" >/dev/null
