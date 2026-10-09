# RetroBB — agent workspace notes (read this first)

Pure-PHP forum (no framework). MIT. Production code + tests must stay green.

## Where things live

- **Repo root is the directory containing this file** (dedicated workspace; moved
  here from inside RandomProjects on 2026-10-08). NEVER use the old nested path.
- Always confirm with `pwd` and `git remote -v` (must be techwiz18/retrobb)
  before any git operation.
- The parent RandomProjects dir is a DIFFERENT repo. A past `git add -A` there
  caused a bad push — never stage/commit outside this repo.

## Running commands

- Run the shell with working directory set to the repo root.
- Dev scripts live in `scripts/` and run from the repo root: `sh scripts/serve.sh`,
  `python3 scripts/smoke.py`, etc. Test suites accept `RETROBB_TEST_BASE` env.
- If file tools fail on paths, use the shell from the repo root instead.

## Infrastructure (Docker)

- `retrobb` container → live board on :8080 (`scripts/serve.sh`, image
  `retrobb-php`, network `retrobb-net`).
- `retrobb-mysql` → MySQL 8, persistent. Databases: `retrobb_live` (USER DATA,
  never drop), `retrobb` (scratch for backend tests), `retrobb_wipe` (empty
  staging for installer tests).
- Test creds: admin/admin123; seeded members use password123.
- MySQL dev creds: host `retrobb-mysql`, user/pass `retrobb`/`retrobb`.

## Test discipline (learned the hard way)

- Write-tests mutate the DB: back up first, restore after, then re-verify.
  The user's live data is sacred.
- The user tests concurrently in their browser — page-text counts race; assert
  on DB truth instead.
- `require_once` is for class files ONLY. `config.php` returns a value, so it
  needs plain `require` (require_once hands back `true` on second inclusion
  and 500s logged-in users).
- `install.php` self-renames to `install.disabled.php` on success; restore the
  name to re-test the wizard.
- Never commit/push without `git status` plus a remote check inside THIS repo.

## Current state (2026-10-08)

- Pre-release `v0.2.0-beta.1` published. MySQL-only backend. Installer wizard live.
- Open threads: 0.3 batch (alerts/reactions/PMs) not started; user walkthroughs
  in progress.
