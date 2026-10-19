#!/usr/bin/env python3
"""Assert that README claims match the source they describe.

Motivated by a run of doc bugs that code review never catches, because the
README is prose and nothing checked it:

  * a version named in the README that no CHANGELOG entry exists for
    (an invented "v0.5.1")
  * a "current release" that had drifted from RETROBB_VERSION
  * a seed credential (`admin`/`admin123`) presented as if it were the
    account the web installer creates
  * a backticked path that does not exist (a file cited that shipped nowhere)
  * a test count that no longer matched what the suite reports

Only mechanically decidable claims are checked here. Judgement calls --
"is this sentence still true in spirit" -- remain a human/review job.

Usage:
  python3 scripts/doccheck.py            # static checks
  python3 scripts/doccheck.py --live     # also run vulntest and compare counts
                                         # (needs a board on $RETROBB_TEST_BASE)

Exit 0 = claims consistent. Exit 1 = a claim contradicts source. Exit 2 = setup.
"""
from __future__ import annotations

import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
README = ROOT / "README.md"
CHANGELOG = ROOT / "CHANGELOG.md"
HELPERS = ROOT / "core" / "helpers.php"

# Backticked tokens that legitimately name something with no repo path:
# URL routes, placeholders, and gitignored runtime dirs.
ALLOW = {
    "/topic/...",
    "/topic/welcome-to-retrobb.t1",
    "storage/",
}

# Files the README tells the reader to CREATE, and gitignored runtime paths.
# Their absence is correct in a fresh clone, so it is not an error. A typo of
# a TRACKED path is still caught, because it is in neither set and does not
# resolve.
EXEMPT = {
    "config.php",       # gitignored; the reader creates it from the example
    "bootstrap.php",    # a plugin author creates this, per the plugin example
    "plugin.json",      # likewise
    "storage/",         # gitignored runtime dir
}


def tracked_files() -> set[str]:
    r = subprocess.run(
        ["git", "ls-files"], cwd=ROOT, capture_output=True, text=True, check=False
    )
    return set(r.stdout.split())


def strip_fences(text: str) -> str:
    """Drop fenced code blocks: they hold examples the reader types or
    creates (a plugin's bootstrap.php), not repo paths to verify."""
    return re.sub(r"```.*?```", "", text, flags=re.S)


def looks_like_path(token: str) -> bool:
    """A repo path, not a shell command, URL, or English phrase."""
    if "://" in token or " " in token or "\t" in token:
        return False
    if any(ch in token for ch in "><|&$=*;"):
        return False
    if token.startswith("/"):
        return False
    return "/" in token or token.endswith((".php", ".py", ".md", ".sql"))


def resolves(token: str, tracked: set[str]) -> bool:
    """Tracked-in-git, present on disk, or an intentionally-exempt name."""
    if token in EXEMPT or token in tracked:
        return True
    if (ROOT / token).exists():
        return True
    if "/" not in token and (ROOT / "scripts" / token).exists():
        return True
    return False

failures: list[str] = []


def fail(msg: str) -> None:
    failures.append(msg)
    print(f"  FAIL {msg}")


def ok(msg: str) -> None:
    print(f"  ok   {msg}")


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def check_version_agreement(readme: str) -> None:
    """The 'current pre-release' line must equal RETROBB_VERSION."""
    m = re.search(r"const RETROBB_VERSION = '([^']+)'", read(HELPERS))
    if not m:
        fail("could not parse RETROBB_VERSION from core/helpers.php")
        return
    version = m.group(1)

    # The README should name the current version somewhere.
    if version in readme:
        ok(f"current version {version} is named in the README")
    else:
        fail(f"RETROBB_VERSION {version} appears nowhere in the README")

    # Nothing may claim to be the current release under a different number.
    claimed = re.findall(r"Current pre-release[^\n]*`([^`]+)`", readme)
    for c in claimed:
        if c != version:
            fail(f"README calls {c} the current pre-release; source says {version}")


def check_versions_exist(readme: str, changelog: str) -> None:
    """Every fully-qualified version in the README needs a CHANGELOG entry."""
    # x.y.z or x.y.z-beta.n / x.y.z-rc.n etc.
    tokens = set(re.findall(r"\b(\d+\.\d+\.\d+(?:-[a-z]+\.\d+)?)\b", readme))
    for t in sorted(tokens):
        if t in changelog:
            ok(f"version {t} has a CHANGELOG entry")
        else:
            fail(f"README names version {t} with no matching CHANGELOG entry")


def check_roadmap_shipped(readme: str, changelog: str) -> None:
    """Versions under 'Shipped' must actually have shipped."""
    section = re.search(
        r"\*\*Shipped\*\*(.*?)(?=\n\*\*|\Z)", readme, re.S
    )
    if not section:
        return
    for minor in re.findall(r"\*\*v(\d+\.\d+)\*\*", section.group(1)):
        if re.search(rf"^## \[{re.escape(minor)}\.", changelog, re.M):
            ok(f"roadmap marks v{minor} shipped and CHANGELOG agrees")
        else:
            fail(f"roadmap marks v{minor} shipped but no CHANGELOG entry exists")


def check_paths(readme: str) -> None:
    """Backticked path-like tokens must exist (or be intentionally exempt)."""
    body = strip_fences(readme)
    tracked = tracked_files()
    tokens = set()
    for raw in re.findall(r"`([^`\n]+)`", body):
        t = raw.strip().rstrip(".,;:")
        if t in ALLOW or not looks_like_path(t):
            continue
        tokens.add(t)
    for t in sorted(tokens):
        if resolves(t, tracked):
            ok(f"path {t} exists")
        else:
            fail(f"README cites `{t}` but it does not exist")


def check_seed_credentials(readme: str) -> None:
    """The seed password must never be presented as the installer's account."""
    if "admin123" not in readme:
        return
    hits = [
        ln for ln in readme.splitlines() if "admin123" in ln
    ]
    # Require the mention to be scoped to the command-line path.
    scoped = any(
        re.search(r"command[- ]line|Option B|seed", ln, re.I) for ln in hits
    )
    if scoped:
        ok("seed credentials are scoped to the command-line path")
    else:
        fail(
            "README states admin/admin123 without scoping it to the CLI seed "
            "-- the web installer creates the account the user types"
        )


def check_test_count(readme: str, live: bool) -> None:
    """The claimed vulntest count must match the reported count."""
    m = re.search(r"vulntest\.py`? covers (\d+) security checks", readme)
    if not m:
        return
    claimed = int(m.group(1))
    if not live:
        print(f"  --   test count {claimed} (run with --live to verify)")
        return
    script = ROOT / "scripts" / "vulntest.py"
    env = dict(os.environ)
    r = subprocess.run(
        [sys.executable, str(script)], capture_output=True, text=True, env=env
    )
    got = re.search(r"RESULT: (\d+) passed", r.stdout)
    if not got:
        fail("could not parse vulntest result (is the board running?)")
        return
    actual = int(got.group(1))
    if actual == claimed:
        ok(f"test count {claimed} matches vulntest's report")
    else:
        fail(f"README claims {claimed} security checks; suite reports {actual}")


def main() -> int:
    if not README.exists():
        print("SETUP: README.md not found")
        return 2
    readme = read(README)
    changelog = read(CHANGELOG) if CHANGELOG.exists() else ""
    live = "--live" in sys.argv

    print("doccheck: README claims vs source")
    print("- version agreement")
    check_version_agreement(readme)
    print("- versions named")
    check_versions_exist(readme, changelog)
    print("- roadmap 'shipped' claims")
    check_roadmap_shipped(readme, changelog)
    print("- cited paths")
    check_paths(readme)
    print("- seed credentials")
    check_seed_credentials(readme)
    print("- test count")
    check_test_count(readme, live)

    if failures:
        print(f"\nRESULT: {len(failures)} claim(s) contradict source")
        return 1
    print("\nRESULT: README claims consistent with source")
    return 0


if __name__ == "__main__":
    sys.exit(main())
