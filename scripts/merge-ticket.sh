#!/bin/sh
# Merge one ticket branch into the current integration branch, safely.
#
#   sh scripts/merge-ticket.sh <branch> ["merge message"]
#
# Refuses a dirty tree or a non-integration branch, records the pre-merge tip,
# merges --no-ff, then asserts the old tip stayed an ancestor (a merge must
# only add history). Prints the real new SHA — a merge report's SHA is
# untrusted until git prints it.
set -e
BR="${1:?usage: sh scripts/merge-ticket.sh <branch> [message]}"
MSG="${2:-merge: $BR}"
ROOT="$(pwd)"

if [ ! -f "${ROOT}/bin/migrate.php" ]; then
    echo "merge-ticket: run this from the repo root" >&2
    exit 2
fi
if [ -n "$(git status --porcelain)" ]; then
    echo "merge-ticket: working tree is dirty — commit or discard first" >&2
    exit 2
fi
CUR="$(git rev-parse --abbrev-ref HEAD)"
case "$CUR" in
    spec/*|integration/*) ;;
    *) echo "merge-ticket: not on an integration branch (on $CUR)" >&2; exit 2 ;;
esac
git rev-parse --verify --quiet "$BR^{commit}" >/dev/null || {
    echo "merge-ticket: no such branch: $BR" >&2; exit 2
}

OLD="$(git rev-parse HEAD)"
git merge --no-ff --no-edit -m "$MSG" "$BR"
NEW="$(git rev-parse HEAD)"

git merge-base --is-ancestor "$OLD" "$NEW" || {
    echo "merge-ticket: merge rewrote history ($OLD is not an ancestor of $NEW)" >&2
    exit 1
}
git merge-base --is-ancestor "$BR" "$NEW" || {
    echo "merge-ticket: $BR is not contained in HEAD after the merge" >&2
    exit 1
}
echo "merge-ticket: $BR merged at $NEW"
