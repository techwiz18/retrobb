# Live deployment runbook — forum.techwiz.dad via hub proxy

This branch (`live`) is the deployable board: `main` plus the site-only
`plugins/hub-shell/` chrome. A RetroBB session stages updates here by
merging `main`; this session deploys them.

## Hard rules (learned the hard way)
- The `live` branch must always share history with `origin/live`. Verify
  with `git merge-base --is-ancestor origin/live live` before pushing.
  NEVER `--allow-unrelated-histories`, NEVER force-push: a divergent local
  branch gets discarded and re-cloned, not pushed.
- No SSH, no credential files, no server-side writes. Backups run through
  cPanel UI by the owner; DB work runs through AdminCP or `php
  bin/migrate.php` over an existing session. Never create `~/.ssh` keys,
  `my.cnf` files, or anything containing a password on the server. If one
  appears, delete it and rotate the exposed credential immediately.
- Backups live OUTSIDE the web root (`~/retrobb-backups/` is fine) and must
  never contain credential files.

## Never commit here
- `config.php` (DB credentials — gitignored, keep it that way)
- `storage/` dumps, logs, sqlite files (gitignored)
- `public/avatars/*` uploads (gitignored)
- `public/.htaccess` redirect hacks — canonical routing lives in code now
  (`force_board_host` + `trusted_proxy_header` in `config.php`)

## Update flow
1. Back up: database dump + copy of `config.php`. Confirm the backup.
2. `git pull` (branch `live`) into a staging copy — or deploy straight from
   cPanel Git, then verify before calling it done.
3. `git status` must show NO modifications to tracked files. If it does,
   stop: a live edit needs folding into the repo first, not overwriting.
4. Run database updates: AdminCP → Dashboard → Run database updates
   (super admin only), or `php bin/migrate.php` via SSH.
5. Delete `public/install.php` if the upload restored it — it must never
   sit on a live board, and it is never run for updates (migrations cover
   that, step 4). The dashboard warns while it exists.
6. Smoke checks (all through `https://techwiz.dad/forum/`):
    - index renders, a forum page renders (not 404), a topic renders
    - `sitemap.xml` locs carry the single `/forum/` prefix, no doubling
    - CSS loads styled (not raw HTML look), login page renders
7. If anything breaks: restore the database dump, check out the previous
   tag/release, re-run smoke checks.

## First-time cPanel setup
- Back up `public_html` fully first. Point cPanel Git deployment at branch
  `live`. After the first checkout, verify these survived untouched:
  `config.php`, `storage/` (lock, mail log, sqlite), `public/avatars/`,
  and that `plugins/hub-shell/` arrived from the branch.
- Remove the old `.htaccess` subdomain-redirect block only AFTER
  `force_board_host` + `trusted_proxy_header` are set in `config.php`
  and a direct subdomain URL 301s to the right hub path (no doubling).
- The Worker must send `X-Hub-Worker: 1` on origin subrequests.
