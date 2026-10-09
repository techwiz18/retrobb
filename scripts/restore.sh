#!/bin/sh
# Restore live DB from a backup (runs cp as root via docker since sqlite is root-owned).
ROOT="$(pwd)"
SRC="${1:-backup-pre02-verify.sqlite}"
docker run --rm -v "${ROOT}:/app" -w /app php:8.3-cli cp "/app/storage/${SRC}" /app/storage/retrobb.sqlite
ls -la "${ROOT}/storage/"
