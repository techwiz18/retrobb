#!/usr/bin/env python3
"""core/Guard.php is the only place a request is refused (issue #39).

A refusal must not be invented per controller: no 403/404/419 status, no error
view, no hand-rolled CSRF check, and no bare refusal body. Those four shapes
are scanned across the PHP that serves the board; the shared module is the one
allowed home.

Not in scope: bin/*.php (their CLI web-exposure 404 is bincheck's subject) and
public/install.php (a standalone installer with its own request handling).
views/errors/* are the templates Guard renders, so they are allowed by design.

    python3 scripts/guardcheck.py     # static; no board needed

Exit 0 = the contract holds. Exit 1 = a controller refuses on its own.
"""
import os
import re
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = Path(__file__).resolve().parent.parent
SEAM = ROOT / 'core' / 'Guard.php'
ROOTS = ['controllers', 'core', 'plugins']

RULES = [
    ('refusal status', re.compile(r'http_response_code\s*\(\s*(?:403|404|419)\b')),
    ('refusal view', re.compile(r"View::render\s*\(\s*['\"]errors/(?:403|404|refused)['\"]")),
    ('hand-rolled CSRF check', re.compile(r'Csrf::verify\s*\(')),
    ('bare refusal body', re.compile(r"echo\s+['\"](?:Forbidden|CSRF mismatch)")),
]


def sources():
    for root in ROOTS:
        for path in sorted((ROOT / root).rglob('*.php')):
            if path == SEAM:
                continue
            yield path


violations = []
for path in sources():
    for lineno, line in enumerate(path.read_text(encoding='utf-8').splitlines(), 1):
        for label, pattern in RULES:
            if pattern.search(line):
                violations.append('%s:%d [%s] %s' % (
                    path.relative_to(ROOT), lineno, label, line.strip()))

check('Guard.php is the only refusal renderer', not violations,
      '\n  ' + '\n  '.join(violations) if violations else '')

# Guard must actually own each rendered shape, or the scan above proves nothing.
seam = SEAM.read_text(encoding='utf-8')
for label, pattern in RULES[:3]:
    check('Guard owns ' + label, bool(pattern.search(seam)))

finish()
