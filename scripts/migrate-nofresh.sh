#!/bin/sh
ROOT="$(pwd)"
docker run --rm -v "${ROOT}:/app" -w /app php:8.3-cli php bin/migrate.php
