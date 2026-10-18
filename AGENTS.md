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
- `retrobb-mailpit` → dev SMTP sink + inbox UI on :8025 (started by
  `serve.sh`; PHP `mail()` relays via msmtp — nothing leaves the machine).
- `retrobb-mysql` → MySQL 8, persistent via the `retrobb-mysql-data` volume
  (survives reboots/recreates — verified 2026-10-10 after a wipe scare). Databases: `retrobb_live` (USER DATA,
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
- **Check the branch before every commit.** `git checkout live` for a deploy
  merge leaves you on `live`; a follow-up `git add`/`commit` then lands the
  feature commit on the deploy branch and `main` silently misses it. Run
  `git branch --show-current` immediately before committing, not just before
  pushing.
- `avatartest`'s "valid upload ok: False" is normal in the dev container: it
  uploads 100×100 while the test DB caps at 90×90, and the image has no GD, so
  the code correctly rejects instead of resizing. Only treat it as a failure
  if the caps are larger than the fixture.

## Current state (2026-10-18)

- **`v0.5.0-beta.2`** on `main` (`03ba805`) and merged into `live` (`ce2337b`).
  Beta.2 is a security/performance pass: server-side session expiry, search
  page cap, paginated members/bans, `COUNT(*)` drafts, sitemap caching +
  304s, blank-`board_url` dashboard warning, and `LOWER()`-free login/mention
  lookups. No migrations.
- `live` = `main` + site-only files (`.cpanel.yml`, `DEPLOY.md`,
  `plugins/hub-shell/`). Deploy = push `live`, then cPanel **Update from
  Remote → Deploy**; the operator clicks that, not the agent.
- Open issues: **#1** mobile responsiveness, **#2** state-changing GETs
  (import-run is the real one), **#8** trusted-proxy header binding (the
  `board_url` half shipped in beta.2).
- Remaining roadmap work is in README (v0.4→v0.7); changelog Ideas is the raw
  backlog.
- Suites: `smoke`, `vulntest` (43 checks), `supertest`, `avatartest`,
  `queuecheck`, `themecheck`, `modflow`, and `backlogcheck` (new, covers the
  beta.2 behaviour).
