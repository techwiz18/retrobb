#!/usr/bin/env python3
"""Assert the main/live branch invariant.

`live` is the deployable branch: `main` plus a small set of site-only files.
The invariant is expressed as two commit-reachability rules, deliberately NOT
as a file diff -- a diff cannot distinguish "main is ahead, about to merge"
(perfectly normal) from "live has a change main never got" (real drift).

  RULE 1 (deploy completeness)  live..main is empty.
          Every commit on main is reachable from live. Catches a fix that
          lands on main and silently misses the deploy.

  RULE 2 (nothing stranded)     every non-merge commit in main..live touches
          only whitelisted site-only paths. Catches a product change made on
          live that main never received -- the "vice versa" direction.

Both rules are evaluated by walking commits, so pushing a new commit to main
never trips them: that commit is on main only and appears in neither set until
it is merged into live.

Usage:
  python3 scripts/branchcheck.py                  # full check, local refs
  python3 scripts/branchcheck.py --remote         # full check, origin refs
  python3 scripts/branchcheck.py --rule deploy    # RULE 1 only
  python3 scripts/branchcheck.py --rule stranded  # RULE 2 only
  python3 scripts/branchcheck.py --install-hook   # install the pre-push gate

Exit 0 = invariant holds. Exit 1 = violation. Exit 2 = setup problem.
"""
from __future__ import annotations

import subprocess
import sys

# Files that legitimately exist only on `live`: one hosting account's deploy
# plumbing and site chrome. Anything else on live but not main is real drift.
SITE_ONLY_PREFIXES = (
    "plugins/hub-shell/",
)
SITE_ONLY = {
    ".cpanel.yml",
    "DEPLOY.md",
}

HOOK = r"""#!/bin/sh
# pre-push gate: python3 scripts/branchcheck.py --install-hook
#
# push to live -> BOTH rules. RULE 1 stops a deploy that is missing main's
#                 fixes; RULE 2 stops a deploy of product work that main never
#                 received (the wrong-branch commit, which would then be lost).
# push to main -> RULE 2 only. RULE 1 is legitimately failing at that moment
#                 (main is ahead of live until the merge), so checking it here
#                 would block the normal flow.
#
# Both pushes also run scripts/doccheck.py: the README must agree with the
# source it describes (version, cited paths, seed credentials, test count).
# Those are the mistakes code review never catches, because docs are prose.
#
# Bypass once with: git push --no-verify
status=0
while read -r local_ref local_sha remote_ref remote_sha; do
  case "$remote_ref" in
    refs/heads/main) rule=stranded ;;
    refs/heads/live) rule=both ;;
    *) continue ;;
  esac
  case "$local_sha" in 0000000000000000000000000000000000000000) continue ;; esac
  if ! out=$(python3 scripts/branchcheck.py --rule "$rule" 2>&1); then
    echo "$out"
    echo ""
    echo "PUSH BLOCKED ($remote_ref). Fix the above, or bypass with --no-verify."
    status=1
  fi
  if ! out=$(python3 scripts/doccheck.py 2>&1); then
    echo "$out"
    echo ""
    echo "PUSH BLOCKED (docs). Fix the above, or bypass with --no-verify."
    status=1
  fi
done
exit $status
"""


def git(*args: str) -> str:
    r = subprocess.run(["git", *args], capture_output=True, text=True, check=False)
    if r.returncode != 0:
        raise RuntimeError(f"git {' '.join(args)} failed: {r.stderr.strip()}")
    return r.stdout


def ref_exists(ref: str) -> bool:
    return (
        subprocess.run(
            ["git", "rev-parse", "--verify", "--quiet", ref], capture_output=True
        ).returncode
        == 0
    )


def is_site_only(path: str) -> bool:
    return path in SITE_ONLY or path.startswith(SITE_ONLY_PREFIXES)


def install_hook() -> int:
    path = git("rev-parse", "--git-path", "hooks/pre-push").strip()
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(HOOK)
    subprocess.run(["chmod", "+x", path], check=True)
    print(f"installed pre-push gate at {path}")
    print("  push to live -> RULE 1 + RULE 2 (deploy completeness, nothing stranded)")
    print("  push to main -> RULE 2 (nothing stranded on live)")
    print("  both pushes  -> doccheck (README claims match source)")
    return 0


def main() -> int:
    if "--install-hook" in sys.argv:
        return install_hook()

    remote = "--remote" in sys.argv
    main_ref = "origin/main" if remote else "main"
    live_ref = "origin/live" if remote else "live"

    rule = ""
    if "--rule" in sys.argv:
        idx = sys.argv.index("--rule")
        if idx + 1 >= len(sys.argv):
            print("SETUP: --rule needs deploy|stranded")
            return 2
        rule = sys.argv[idx + 1]
        if rule not in ("deploy", "stranded", "both"):
            print(f"SETUP: unknown --rule {rule!r} (deploy|stranded|both)")
            return 2

    for ref in (main_ref, live_ref):
        if not ref_exists(ref):
            print(f"SETUP: ref {ref} does not exist")
            return 2

    where = "remote" if remote else "local"
    print(f"branchcheck: {main_ref} vs {live_ref} ({where})")
    failures = 0

    # ---- RULE 1: deploy completeness (live..main must be empty) ----------
    if rule in ("", "deploy", "both"):
        behind = git("rev-list", "--count", f"{live_ref}..{main_ref}").strip()
        if behind != "0":
            failures += 1
            print(
                f"\nFAIL deploy: {behind} commit(s) on {main_ref} "
                f"not in {live_ref}"
            )
            print("  These fixes are NOT deployed. Merge main into live and push:")
            for line in git(
                "log", "--oneline", f"{live_ref}..{main_ref}"
            ).strip().splitlines():
                print(f"    {line}")
        else:
            print("  ok  deploy: every commit on main is in live")

    # ---- RULE 2: nothing stranded on live (whitelist-only commits) -------
    if rule in ("", "stranded", "both"):
        # --no-merges: a merge commit that pulls main in is not new work.
        raw = git(
            "log", "--no-merges", "--name-only", "--format=@@%h %s",
            f"{main_ref}..{live_ref}",
        )
        stranded: dict[str, list[str]] = {}
        current = ""
        for line in raw.splitlines():
            line = line.strip()
            if not line:
                continue
            if line.startswith("@@"):
                current = line[2:]
            elif not is_site_only(line):
                stranded.setdefault(line, []).append(current)

        if stranded:
            failures += 1
            print(
                f"\nFAIL stranded: {len(stranded)} product file(s) changed "
                f"on {live_ref} but never on {main_ref}"
            )
            print("  This work would be lost the moment live is rebuilt from main:")
            for path, commits in sorted(stranded.items()):
                print(f"    {path}")
                for commit in commits:
                    print(f"        from {commit}")
        else:
            print(
                "  ok  stranded: every live-only commit touches site-only files"
            )

    if failures:
        print(f"\nRESULT: {failures} violation(s)")
        return 1
    print("\nRESULT: invariant holds")
    return 0


if __name__ == "__main__":
    sys.exit(main())
