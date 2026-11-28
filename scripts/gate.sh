#!/bin/sh
# Build an isolated test board and run suites against those same values.
#
#   sh scripts/gate.sh <ns 0-99> [suite ...]
#
# The namespace derives the DB, board port and the install/bin ports
# (see scripts/testenv.sh), so parallel worktrees cannot collide. The values
# are written to storage/test.env and sourced here — never re-typed by hand,
# which is how a run once served retrobb_test while writing to retrobb_t0.
set -e
NS="${1:?usage: sh scripts/gate.sh <ns 0-99> [suite ...]}"
shift
ROOT="$(pwd)"

if [ ! -f "${ROOT}/bin/migrate.php" ]; then
    echo "gate: run this from the repo root" >&2
    exit 2
fi

RETROBB_TEST_NS="$NS" sh scripts/testenv.sh
set -a
. "${ROOT}/storage/test.env"
set +a

exec python3 scripts/runtests.py "$@"
