# Recovering a stranded `live` branch

Read this only when `python3 scripts/branchcheck.py` FAILs `stranded` on
product files — e.g. after a `--no-verify` push or a commit made on the wrong
branch — even when `git diff main..live` shows only site-only files.

The checker walks commits, not the diff, so the offending commit must leave
`live`'s history. Do NOT fix it with another `--no-verify` push.

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
