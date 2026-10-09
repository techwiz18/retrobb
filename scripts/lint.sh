#!/bin/sh
# helper: lint all php via docker (mount needs absolute path, so build it at runtime)
ROOT="$(pwd)"
docker run --rm -v "${ROOT}:/app" -w /app php:8.3-cli sh -c 'for f in $(find core models controllers bin public -name "*.php"); do php -l "$f" || exit 1; done'
