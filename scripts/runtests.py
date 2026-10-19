#!/usr/bin/env python3
"""Run the RetroBB suites against a test board; exit non-zero on any failure.

This is the single entry point for the regression suite (issue #9). It runs
each suite in scripts/, points it at the isolated test board, and fails the
whole run if any suite fails.

Prereq: a test board is up (scripts/testenv.sh builds it: fresh, seeded,
canonical ids).

    scripts/testenv.sh                 # or: python3 scripts/runtests.py --up
    python3 scripts/runtests.py        # all suites, http://localhost:8081
    python3 scripts/runtests.py smoke vulntest     # a subset
    RETROBB_TEST_BASE=... RETROBB_TEST_DB=... python3 scripts/runtests.py

A suite counts as failed if it exits non-zero or prints a FAIL/Traceback
marker (a backstop for any suite not yet converted to scripts/_harness.py).
"""
import os
import re
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BASE = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8081')
DB = os.environ.get('RETROBB_TEST_DB', 'retrobb_test')

# Order is incidental — suites are self-contained (they seed/discover ids and
# clean up shared state) so they pass in any order.
#
# Deliberately NOT here: installflow (drives the web installer against its own
# empty DBs/board, not this fixture), avatartest (needs an image-capable PHP —
# the dev image has no GD), and v2test (superseded by v22test/v23test).
SUITES = [
    'smoke',
    'vulntest',
    'supertest',
    'backlogcheck',
    'queuecheck',
    'modflow',
    'sessiontest',
    'themecheck',
    'lazytest',
    'v22test',
    'v23test',
    'bugtest',
    'deltest',
    'readonly',
    'admincheck',
    'concurrency',
    'viewcount',
    'viewstrict',
]


def run_suite(name):
    """Run one suite. Returns (ok, seconds, tail_lines)."""
    path = ROOT / 'scripts' / (name + '.py')
    if not path.is_file():
        return False, 0.0, ['missing: scripts/%s.py' % name]
    env = dict(os.environ)
    env['RETROBB_TEST_BASE'] = BASE
    env['RETROBB_TEST_DB'] = DB
    start = time.time()
    try:
        proc = subprocess.run(
            [sys.executable, str(path)], cwd=str(ROOT),
            capture_output=True, text=True, env=env, timeout=600,
        )
        out = (proc.stdout or '') + (proc.stderr or '')
        rc = proc.returncode
    except subprocess.TimeoutExpired:
        return False, 600.0, ['TIMEOUT after 600s']
    secs = time.time() - start

    ok = rc == 0
    if 'Traceback (most recent call last)' in out:
        ok = False
    if re.search(r'^FAIL(ED)?\b', out, re.M):
        ok = False

    tail = [ln for ln in out.strip().splitlines() if ln.strip()][-4:]
    return ok, secs, tail


def main(argv):
    suites = [a for a in argv if not a.startswith('-')]
    if '--up' in argv:
        print('runtests: building test environment (scripts/testenv.sh)…\n')
        r = subprocess.run(['sh', 'scripts/testenv.sh'], cwd=str(ROOT))
        if r.returncode != 0:
            print('runtests: testenv.sh failed', file=sys.stderr)
            return 2
        print()
    if not suites:
        suites = SUITES

    print('runtests: %s  (db %s)' % (BASE, DB))
    print('-' * 60)
    results = []
    for name in suites:
        ok, secs, tail = run_suite(name)
        results.append((name, ok, secs))
        print('%-14s %-4s  %5.1fs' % (name, 'ok' if ok else 'FAIL', secs))
        if not ok:
            for ln in tail:
                print('              | ' + ln)

    failed = [n for n, ok, _ in results if not ok]
    print('-' * 60)
    print('%d/%d suites passed' % (len(results) - len(failed), len(results)))
    if failed:
        print('FAILED: ' + ', '.join(failed))
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
