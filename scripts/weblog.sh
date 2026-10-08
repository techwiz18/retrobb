#!/bin/sh
docker logs retrobb-mysql-web 2>&1 | tail -15
docker logs retrobb-mysql-web 2>&1 | wc -l
