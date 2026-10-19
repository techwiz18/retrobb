#!/bin/sh
# Query a RetroBB MySQL database. Usage: sh scripts/mysql-sql.sh "SELECT ..."
# Target database: $RETROBB_TEST_DB (default "retrobb"). The suites export it
# so DB-truth assertions read the same board they exercise (issue #9).
DB="${RETROBB_TEST_DB:-retrobb}"
docker exec retrobb-mysql mysql -uroot -proot -N -B "$DB" -e "$1" 2>/dev/null
