import os, re, subprocess, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    url = path if path.startswith('http') else base + path
    r = op.open(url)
    return r.url, r.read().decode()

def post(op, path, fields):
    req = urllib.request.Request(base + path, data=urllib.parse.urlencode(fields, doseq=True).encode())
    r = op.open(req)
    return r.url, r.read().decode()

def csrf(html):
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    return m.group(1) if m else None

def anon_csrf(html):
    m = re.search(r'name="anon_csrf" value="([^"]+)"', html)
    return m.group(1) if m else None

def set_theme(op, t):
    # theme switch is POST + sessionless CSRF now (issue #2)
    _, html = get(op, '/')
    post(op, '/theme/' + t, {'anon_csrf': anon_csrf(html)})

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

for path, marker in [('/admin', 'Dashboard'), ('/admin/settings', 'Board name'), ('/admin/spam', 'CAPTCHA provider'),
                     ('/admin/structure', 'Add category'), ('/admin/bans', 'Bans'), ('/admin/users', 'dialup_dan'),
                     ('/admin/modlog', 'Mod log')]:
    _, html = get(admin, path)
    check('admin page %s' % path, marker in html)

# view-once per session, asserted on DB truth (page text races with live browsing)
_, html = get(admin, '/new-topic/2')
url, _ = post(admin, '/new-topic/2', {'csrf': csrf(html), 'title': 'V23 views probe', 'body': 'count me once'})
vtid = int(re.search(r'\.t(\d+)', url).group(1))
sA = session()
get(sA, url); a = views_db(vtid)
get(sA, url); b = views_db(vtid)
sB = session()
get(sB, url); c = views_db(vtid)
check('views counted once per session', b == a and c == a + 1, '%s %s %s' % (a, b, c))

# profile editor as dialup_dan
mem = session()
login(mem, 'dialup_dan', 'password123')
_, ehtml = get(mem, '/settings/profile')
check('profile editor form renders', 'Bio' in ehtml and 'Change password' in ehtml)
_, ehtml2 = post(mem, '/settings/profile', {'csrf': csrf(ehtml), 'form': 'profile', 'email': 'dan@example.com', 'bio': 'v23 test bio line'})
check('bio saved', 'v23 test bio line' in ehtml2)
_, ehtml = get(mem, '/settings/profile')
_, ehtml2 = post(mem, '/settings/profile', {'csrf': csrf(ehtml), 'form': 'password', 'current': 'password123', 'new': 'newpass123', 'new2': 'newpass123'})
check('password changed', 'Password changed' in ehtml2)
mem2 = session()
login(mem2, 'dialup_dan', 'newpass123')
_, home = get(mem2, '/')
check('new password works', 'dialup_dan' in home)
_, ehtml = get(mem2, '/settings/profile')
post(mem2, '/settings/profile', {'csrf': csrf(ehtml), 'form': 'password', 'current': 'newpass123', 'new': 'password123', 'new2': 'password123'})

# sitemap xsl follows theme cookie
dark = session(); set_theme(dark, 'dark')
check('xsl dark', '#14161b' in dark.open(base + '/sitemap.xsl').read().decode())
light = session(); set_theme(light, 'light')
check('xsl light', '#dfe3ee' in light.open(base + '/sitemap.xsl').read().decode())
finish()
