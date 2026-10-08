#!/bin/sh
ROOT="$(pwd)/retrobb"
docker rm -f retrobb 2>/dev/null || true
docker run -d --rm --name retrobb -p 8080:8000 -v "${ROOT}:/app" -w /app php:8.3-cli php -S 0.0.0.0:8000 -t public public/router.php
sleep 2
docker logs retrobb 2>&1 | tail -5
