"""bin/*.php must never run from the web (issue #13 / B1).

Serves the repo root with a routerless PHP server (the package-root layout the
.htaccess is meant to protect) and requests each CLI script directly. The
trusted web path — AdminCP 'Run database updates' — must still work.
"""
import http.cookiejar
import os, re, subprocess, sys, time, urllib.parse, urllib.request, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
NAME = 'retrobb-bincheck'
PORT = 8099
BASE = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8081')
RAW = 'http://localhost:%d' % PORT

def sh(*args):
    return subprocess.run(args, capture_output=True, text=True)

def fetch(url, data=None):
    try:
        r = urllib.request.urlopen(urllib.request.Request(url, data=data), timeout=10)
        return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()

try:
    sh('docker', 'rm', '-f', NAME)
    # Loopback only: the repo root is the docroot, so never publish it.
    sh('docker', 'run', '-d', '--rm', '--name', NAME, '-p', '127.0.0.1:%d:8000' % PORT,
       '-v', ROOT + ':/app', '-w', '/app', 'php:8.3-cli',
       'php', '-S', '0.0.0.0:8000', '-t', '/app')
    for _ in range(30):
        try:
            urllib.request.urlopen(RAW + '/LICENSE', timeout=2).read()
            break
        except Exception:
            time.sleep(0.3)

    for script, marker in [('bin/migrate.php', 'Applying'),
                           ('bin/seed.php', 'Seeded'),
                           ('bin/import-sqlite.php', 'Usage'),
                           ('bin/rebuild-html.php', 'scanned')]:
        code, body = fetch(RAW + '/' + script)
        # An empty 404 is the guard; a missing file would give the built-in
        # HTML 404, so assert the file exists and the body is empty too.
        check('%s refuses web execution' % script,
              os.path.isfile(os.path.join(ROOT, script))
              and code == 404 and body.strip() == '' and marker not in body,
              '%s: %s' % (code, body[:80]))

    # Trusted web path still works: AdminCP database updates.
    cj = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    html = op.open(BASE + '/login').read().decode()
    tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    op.open(urllib.request.Request(BASE + '/login', data=urllib.parse.urlencode(
        {'csrf': tok, 'login': 'admin', 'password': 'admin123'}).encode()))
    html = op.open(BASE + '/admin').read().decode()
    tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    resp = op.open(urllib.request.Request(BASE + '/admin/migrate', data=urllib.parse.urlencode(
        {'csrf': tok}).encode()))
    check('AdminCP database updates still run', 'Database updates ran clean' in resp.read().decode())
finally:
    sh('docker', 'rm', '-f', NAME)

finish()
