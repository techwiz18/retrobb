# Release packaging

Read this before zipping or publishing anything.

- NEVER `zip -r .` from the worktree — it grabs `.opencode/node_modules/`,
  `.git/`, local `config.php`, and mail logs (16 MB leak vs ~150 KB clean).
  A stray root `*.zip` is the tell; delete it, don't commit it (`dist/` is
  gitignored for a reason).
- ONLY build with `sh scripts/package-release.sh <version>` from a clean tree
  (it refuses dirty worktrees unless passed `--worktree`). It allowlists
  `public core controllers models views migrations plugins bin
  config.example.php .htaccess index.php LICENSE` via `git archive`, strips
  `plugins/hub-shell`, `plugins/hello-world`, and `storage/*`, writes to
  `/tmp/opencode/retrobb-<ver>.zip`, and fails on MISSING front door or LEAK
  (`config.php`, hub-shell, hello-world).
- Order: bump `RETROBB_VERSION` in `core/helpers.php:7` on `main`, commit,
  push `main`, merge to `live`, push `live`, tag `v<version>`, push tag, then
  run the script. GitHub pre-release = that tag, not a hand zip.
- Publish the pre-release with `gh` (tag + zip already exist — this only
  creates the Release object, no rebuild/re-tag/re-push):
  1. Verify: `git rev-parse v<ver>` equals `git rev-parse main`;
     `ls -lh /tmp/opencode/retrobb-<ver>.zip` (~170K);
     `python3 scripts/branchcheck.py --remote` holds;
     `gh release list | head` does NOT show the version yet.
  2. `gh release create v<ver> --prerelease --title "v<ver>" --notes "<one
     line: what's in it + install via public/install.php>" /tmp/opencode/retrobb-<ver>.zip`
  3. Verify: `gh release view v<ver>` shows pre-release + the zip asset.
  If `create` says already exists, stop — run `view` only, never
  delete/recreate or move the tag to fix it.
