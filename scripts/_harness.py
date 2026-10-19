"""Shared pass/fail harness for the RetroBB suites (issue #9).

Suites import this so every one reports the same way and exits non-zero when
anything fails — which is what scripts/runtests.py keys off. Dependency-free
on purpose: it ships in the repo, not in a venv.

    from _harness import check, note, finish
    check('login works', 'Log in' not in html)   # asserts, prints PASS/FAIL
    note('discovered topic #%d' % tid)           # informational, never fails
    finish()                                     # prints summary, sys.exit
"""
import sys

PASSED = []
FAILED = []


def check(name, cond, detail=''):
    """Assert `cond`; record and print the result. Returns the boolean."""
    ok = bool(cond)
    (PASSED if ok else FAILED).append(name)
    line = ('PASS ' if ok else 'FAIL ') + name
    if detail != '':
        line += ' — ' + str(detail)
    print(line)
    return ok


def note(text):
    """Informational line. Never affects the exit code."""
    print(text)


def finish():
    """Print the summary and exit 0 (all passed) or 1 (any failed)."""
    print('RESULT: %d passed, %d failed' % (len(PASSED), len(FAILED)))
    if FAILED:
        print('FAILED: ' + ', '.join(FAILED))
    sys.exit(1 if FAILED else 0)
