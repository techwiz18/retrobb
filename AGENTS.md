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
- **Run the regression suite deterministically** (issue #9). One namespace
  derives every name, so parallel worktrees cannot collide:
  `sh scripts/gate.sh <ns 0-99> [suite…]` builds the throwaway board
  (`retrobb_<ns>`, `:81<ns>`, `retrobb-test-<ns>`) and runs the suites against
  the same values, which it writes to `storage/test.env`. `sh scripts/testenv.sh`
  alone only rebuilds the board; `. storage/test.env` gives the matching env.
  Defaults (no NS) are `retrobb_test` on :8081. Suites use `scripts/_harness.py`
  for PASS/FAIL + exit codes. This never touches the dev board (:8080) or
  `retrobb_live`.
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
- **Integrate ticket branches with `sh scripts/merge-ticket.sh <branch>`.** It
  refuses a dirty tree or a non-integration branch, merges `--no-ff`, asserts
  the old tip is still an ancestor (a merge only adds history), and prints the
  real new SHA. A merge report's SHA is untrusted until `git rev-parse` agrees.
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

- **`v0.6.0-beta.2`** (`RETROBB_VERSION` in `core/helpers.php:7`, tagged +
  published as GitHub pre-release with `/tmp/opencode/retrobb-v0.6.0-beta.2.zip`
  from `scripts/package-release.sh`). Adds to beta.1: plugin API (display +
  postbit + routes + lifecycle hooks, `username_html()` everywhere, confirmed
  uninstall), AdminCP-kept mod queue, mobile finish, audit hardening (races,
  transactions, throttle, double-submit).
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

### If `live` is stranded

See `docs/ops/branch-recovery.md`. The offending commit must leave `live`'s
history; never fix it with another `--no-verify` push.

## Release packaging

See `docs/ops/release.md` before zipping or publishing anything. Build only
with `sh scripts/package-release.sh <version>` from a clean tree.

## Agent skills

### Issue tracker

Issues live in this repo's GitHub Issues (`techwiz18/retrobb`). See `docs/agents/issue-tracker.md`.

### Triage labels

Five canonical labels, names unchanged. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `GLOSSARY.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
