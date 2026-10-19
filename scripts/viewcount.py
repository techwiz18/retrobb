import os, re, subprocess, sys, urllib.request, urllib.parse, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    r = op.open(path if path.startswith('http') else base + path)
    return r.url, r.read().decode()

def post(op, path, fields):
    r = op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields, doseq=True).encode()))
    return r.url, r.read().decode()

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

def views_db(tid):
    out = subprocess.run(['sh', 'scripts/mysql-sql.sh', 'SELECT views FROM topics WHERE id=%d' % tid],
                         capture_output=True, text=True, cwd=ROOT,
                         env=dict(os.environ, RETROBB_TEST_DB=os.environ.get('RETROBB_TEST_DB', 'retrobb')))
    return int(out.stdout.strip())

admin = session()
login(admin, 'admin', 'admin123')
_, html = get(admin, '/admin/settings')
post(admin, '/admin/settings', {'csrf': csrf(html), 'flood_seconds': '0', 'return': 'settings'})
_, html = get(admin, '/new-topic/2')
url, _ = post(admin, '/new-topic/2', {'csrf': csrf(html), 'title': 'Viewcount probe', 'body': 'counting views here'})
tid = int(re.search(r'\.t(\d+)', url).group(1))

s1 = session()
get(s1, url); a = views_db(tid)
get(s1, url); b = views_db(tid)
s2 = session()
get(s2, url); c = views_db(tid)
check('views counted once per session', b == a and c == a + 1, '%s %s %s' % (a, b, c))
finish()
