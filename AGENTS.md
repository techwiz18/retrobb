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
- **Run the regression suite deterministically** (issue #9): `sh scripts/testenv.sh`
  rebuilds a throwaway `retrobb_test` DB (migrate + seed, canonical ids) and serves
  it on :8081; `python3 scripts/runtests.py` runs every suite against it in any
  order and exits non-zero on failure. Suites use `scripts/_harness.py` for
  PASS/FAIL + exit codes. This never touches the dev board (:8080) or `retrobb_live`.
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
  The user's live data is sacred. (For the regression suite, `scripts/testenv.sh`
  sidesteps this by using the disposable `retrobb_test` DB.)
- Suites must be **self-contained**: discover or create the ids they need, and
  clean up shared state (reports, bans) so they pass in any order. Hardcoding a
  fixture id or depending on another suite's leftovers is the bug that made the
  old suites fail against drifted databases (issue #9).
- The user tests concurrently in their browser — page-text counts race; assert
  on DB truth instead.
- `require_once` is for class files ONLY. `config.php` returns a value, so it
  needs plain `require` (require_once hands back `true` on second inclusion
  and 500s logged-in users).
- `install.php` deletes itself on success (`unlink`, fallback to
  `storage/install.disabled.php.bak` outside `public/`); restore the
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
- **Never leave a clone shallow.** The deploy checkout at
  `/media/dan/data/ai/RetroBB-Live` was a shallow clone (`.git/shallow` grafted
  at its tip), which made `live` look like an orphan branch: `git merge origin/main`
  failed with "refusing to merge unrelated histories" and the tempting fix,
  `--allow-unrelated-histories`, would have given `live` a second root. Run
  `git fetch --unshallow` on any fresh clone; check with `ls .git/shallow`.

## Current state (2026-10-28)

- **`v0.5.0-beta.2`** (`RETROBB_VERSION` in `core/helpers.php:7`) plus
  unreleased security hardening on `main`: stored-XSS fix + repair path
  (C1), Host-header distrust / `board_url` canonicals + reset-mail refuse
  (H1), installer re-run guard + self-deletion + POST step order (H2),
  `bin/*` web-execution refuse (B1), CI (doccheck + branchcheck + lint +
  full suite). No version bump yet.
- `live` = `main` + site-only files (`.cpanel.yml`, `DEPLOY.md`,
  `plugins/hub-shell/`). Deploy = push `live`, then cPanel **Update from
  Remote → Deploy**; the operator clicks that, not the agent.
- Open issues: **#1** mobile responsiveness; v1.0 milestone tracks the rest
  (#9, #10, #14, C1, H1 closed at last check); #15 theme customization,
  #16 AdminCP Maintenance area. The old #2 import-run GET and #8
  `board_url` items are shipped — see `CHANGELOG.md` Unreleased/Security.
- Remaining roadmap work is in README (v0.4→v0.7); changelog Ideas is the raw
  backlog.
- Suites run via `scripts/runtests.py` (see it for the current list):
  `smoke`, `vulntest`, `supertest`, `avatartest`, `queuecheck`,
  `themecheck`, `modflow`, `backlogcheck`, plus `hostcheck`,
  `installcheck`, `bincheck`, `rebuildcheck` for the security work above.
- **`scripts/branchcheck.py` enforces the main/live invariant** (see
  *Branch discipline* below). Run `--remote` after any push; a pre-push hook
  is installed with `--install-hook`.

## Branch discipline

`live` is `main` plus site-only files. Two rules, both checked by
`python3 scripts/branchcheck.py`:

1. **deploy completeness** — `git rev-list live..main` is empty. Every fix on
   `main` reached the deploy branch.
2. **nothing stranded** — every non-merge commit in `main..live` touches only
   the site-only whitelist (`.cpanel.yml`, `DEPLOY.md`,
   `plugins/hub-shell/`). Catches a product change committed on `live` that
   `main` never received.

These are commit-reachability rules, deliberately not a file diff: a diff
cannot tell "main is one commit ahead, merge pending" (normal) from "live has
work main never got" (drift).

The pre-push hook blocks a dirty `live` push on either rule and a `main` push
on rule 2 only — checking rule 1 when pushing `main` would block the normal
flow. Bypass one push with `--no-verify`. New site-only files under
`plugins/hub-shell/` need no script edit; adding one elsewhere means adding it
to `SITE_ONLY`.

### If `live` is stranded (recovery for any agent)

Symptom: `branchcheck` FAILs `stranded` on product files (e.g. after a
`--no-verify` push or a commit on the wrong branch), even when
`git diff main..live` shows only site-only files. The checker walks commits,
not the diff, so the offending commit must leave `live`'s history — do NOT
fix it with another `--no-verify` push.

1. `git branch --show-current` (know where you are), then
   `git log --oneline --no-merges --reverse main..live` to list live-only
   commits and spot the product-file offender.
2. `git checkout live && git branch backup-live-<date> live` (safety copy).
3. `git reset --hard main` — `live` is now clean.
4. `git cherry-pick` the site-only commits from step 1 in order, skipping
   the offender (e.g. `git cherry-pick c5d8813 5adc946 ...`).
5. Verify: `git diff main..live --stat` shows ONLY site-only files, and
   `python3 scripts/branchcheck.py` prints invariant holds.
6. Push `main` first, then force-push the rebuilt `live`:
   `git push origin main && git push --force-with-lease origin live`.
7. Verify: `python3 scripts/branchcheck.py --remote` must pass. Delete the
   backup branch only after that.
