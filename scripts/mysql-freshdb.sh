#!/bin/sh
# Fresh MySQL database for the install.php test.
docker exec retrobb-mysql mysql -uroot -proot -e "DROP DATABASE IF EXISTS retrobb_fresh; CREATE DATABASE retrobb_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON retrobb_fresh.* TO 'retrobb'@'%'; FLUSH PRIVILEGES;"
echo fresh db ready
