#!/bin/sh
# Give the Apache worker ownership of storage/ (test rig only).
docker exec -u 0 retrobb-apache chown -R www-data:www-data /var/www/retrobb/storage
docker exec retrobb-apache ls -la /var/www/retrobb/storage | head -8
