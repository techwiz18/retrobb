#!/bin/sh
# Remove the install lock inside the web container (test helper).
docker exec -u 0 retrobb rm -f /app/storage/installed.lock && echo lock-removed
