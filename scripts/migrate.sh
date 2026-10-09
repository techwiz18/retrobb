#!/bin/sh
# Run migrations against the dev MySQL database.
ROOT="$(pwd)"
sh "${ROOT}/scripts/mysql-php.sh" php bin/migrate.php "$@"
