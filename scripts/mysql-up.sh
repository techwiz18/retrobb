#!/bin/sh
# MySQL 8 for RetroBB verification. Idempotent-ish.
docker network create retrobb-net 2>/dev/null || true
if ! docker ps --format '{{.Names}}' | grep -q '^retrobb-mysql$'; then
  docker run -d --rm --name retrobb-mysql --network retrobb-net \
    -e MYSQL_ROOT_PASSWORD=root \
    -e MYSQL_DATABASE=retrobb \
    -e MYSQL_USER=retrobb \
    -e MYSQL_PASSWORD=retrobb \
    mysql:8 --mysql-native-password=ON 2>&1 | tail -1 || \
  docker run -d --rm --name retrobb-mysql --network retrobb-net \
    -e MYSQL_ROOT_PASSWORD=root \
    -e MYSQL_DATABASE=retrobb \
    -e MYSQL_USER=retrobb \
    -e MYSQL_PASSWORD=retrobb \
    mysql:8
fi
echo waiting for mysql...
for i in $(seq 1 30); do
  if docker exec retrobb-mysql mysqladmin ping -h127.0.0.1 -uroot -proot --silent 2>/dev/null; then
    echo "mysql up after ${i} tries"; break
  fi
  sleep 2
done
docker exec retrobb-mysql mysqladmin ping -h127.0.0.1 -uroot -proot 2>&1 | tail -1
