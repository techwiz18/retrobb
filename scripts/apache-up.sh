#!/bin/sh
# Apache + PHP test rig for RetroBB .htaccess verification.
# Serves retrobb/public on :8093 with the project above docroot. Idempotent-ish.
docker pull php:8.3-apache 2>&1 | tail -1
docker rm -f retrobb-apache 2>/dev/null || true
ROOT="$(pwd)/retrobb"
docker run -d --rm --name retrobb-apache -p 8093:80 \
  -v "${ROOT}:/var/www/retrobb" \
  -v "${ROOT}/docs/retrobb-vhost.conf:/etc/apache2/sites-enabled/001-retrobb.conf:ro" \
  php:8.3-apache
sleep 1
echo '--- php modules:'
docker exec retrobb-apache php -m | grep -i -E 'sqlite|pdo|mbstring' || true
docker exec retrobb-apache a2dissite 000-default >/dev/null 2>&1
docker exec retrobb-apache a2enmod rewrite
docker restart retrobb-apache >/dev/null
sleep 3
echo '--- ready:'
docker exec retrobb-apache apache2ctl -M 2>/dev/null | grep -E 'rewrite|php' || true
