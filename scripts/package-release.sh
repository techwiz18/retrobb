#!/bin/sh
# Build the lean end-user package: only what installs and runs the forum.
# Usage:
#   sh scripts/package-release.sh v0.3.0-beta.1            # from HEAD (tree must be clean)
#   sh scripts/package-release.sh v0.4-dev --worktree      # current worktree, dev builds
# Output: /tmp/opencode/retrobb-<ver>.zip
set -e
VER="${1:?usage: package-release.sh <version> [--worktree]}"
ROOT="$(pwd)"
OUT="/tmp/opencode/retrobb-${VER}.zip"
# Everything the forum needs to install and run — and nothing else.
# (Dev tooling, agent notes, and docs stay out: docker/ scripts/ AGENTS.md
# README.md CHANGELOG.md docs/ .gitignore .gitattributes)
FILES="public core controllers models views migrations plugins bin config.example.php .htaccess index.php LICENSE"
rm -f "$OUT"
mkdir -p /tmp/opencode /tmp/zipstage
rm -rf /tmp/zipstage/rel && mkdir -p /tmp/zipstage/rel/storage
if [ "$2" = "--worktree" ]; then
  # shellcheck disable=SC2086
  zip -qr "$OUT" $FILES -x '*/storage/*'
else
  if [ -n "$(git status --porcelain | grep -v '^?? .opencode/')" ]; then
    echo "Worktree is dirty — commit first or use --worktree."; exit 1;
  fi
  # shellcheck disable=SC2086
  git archive --format=tar "HEAD" $FILES | tar -x -C /tmp/zipstage/rel
  (cd /tmp/zipstage/rel && zip -qr "$OUT" .)
fi
# Sanity: front door present, no secrets.
rm -rf /tmp/zipstage/chk && mkdir -p /tmp/zipstage/chk
unzip -q -o "$OUT" -d /tmp/zipstage/chk
for f in public/install.php public/index.php index.php .htaccess config.example.php bin/migrate.php LICENSE; do
  [ -e "/tmp/zipstage/chk/$f" ] || { echo "MISSING $f"; exit 1; };
done
if [ -e /tmp/zipstage/chk/config.php ]; then echo "LEAK: config.php"; exit 1; fi
echo "wrote $OUT ($(du -h "$OUT" | cut -f1))"
unzip -l "$OUT" | tail -n 2
