#!/bin/sh
# Query the MySQL test board. Usage: sh scripts/mysql-sql.sh "SELECT ..."
docker exec retrobb-mysql mysql -uroot -proot -N -B retrobb -e "$1" 2>/dev/null
