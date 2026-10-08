#!/bin/sh
ROOT="$(pwd)/retrobb"
docker run --rm -v "${ROOT}:/app" -w /app php:8.3-cli php bin/migrate.php --fresh --seed
ls -la "${ROOT}/storage/"
