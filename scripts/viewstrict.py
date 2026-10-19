import os, re, subprocess, sys, urllib.request, urllib.parse, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
opNR = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(cj))

def sql(q):
    out = subprocess.run(['sh', 'scripts/mysql-sql.sh', q], capture_output=True, text=True, cwd=ROOT,
                         env=dict(os.environ, RETROBB_TEST_DB=os.environ.get('RETROBB_TEST_DB', 'retrobb')))
    return out.stdout.strip()

def get(path):
    r = op.open(base + path)
    return r.url, r.read().decode()

def noreq(path, fields=None):
    data = urllib.parse.urlencode(fields or {}, doseq=True).encode() if fields is not None else None
    try:
        r = opNR.open(urllib.request.Request(base + path, data=data))
        return r.status, r.headers.get('Location')
    except Exception as e:
        return getattr(e, 'code', '?'), (e.headers.get('Location') if hasattr(e, 'headers') else None)

html = op.open(base + '/login').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
op.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
    {'csrf': tok, 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()))
html = op.open(base + '/new-topic/2').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
code, loc = noreq('/new-topic/2', {'csrf': tok, 'title': 'Nofollow probe', 'body': 'counting strictly here'})
check('bare POST creates topic (302 to it)', code == 302 and '.t' in (loc or ''), '%s -> %s' % (code, loc))
tid = int(re.search(r'\.t(\d+)', loc).group(1))
v0 = int(sql('SELECT views FROM topics WHERE id=%d' % tid))
get('/topic/nofollow-probe.t%d' % tid)
v1 = int(sql('SELECT views FROM topics WHERE id=%d' % tid))
get('/topic/nofollow-probe.t%d' % tid)
v2 = int(sql('SELECT views FROM topics WHERE id=%d' % tid))
check('first GET counts a view', v1 == v0 + 1, '%s -> %s' % (v0, v1))
check('second same-session GET does not', v2 == v1, '%s -> %s' % (v1, v2))
finish()
