import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

admin = session()

def get(op, path):
    url = path if path.startswith('http') else base + path
    r = op.open(url)
    return r.url, r.read().decode()

def post(op, path, fields):
    data = urllib.parse.urlencode(fields, doseq=True).encode()
    req = urllib.request.Request(base + path, data=data)
    r = op.open(req)
    return r.url, r.read().decode()

def csrf(html):
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    return m.group(1) if m else None

def login(op, u, p):
    _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

def views(html):
    m = re.search(r'(\d+) views', html)
    return int(m.group(1)) if m else -1

def views_db(tid):
    import subprocess
    out = subprocess.run(['sh', 'retrobb/scripts/mysql-sql.sh', 'SELECT views FROM topics WHERE id=%d' % tid],
                         capture_output=True, text=True, cwd='/media/dan/data/ai/RandomProjects')
    return int(out.stdout.strip())

login(admin, 'admin', 'admin123')

# admin pages
for path, marker in [('/admin', 'Dashboard'), ('/admin/settings', 'Board name'), ('/admin/spam', 'CAPTCHA provider'),
                     ('/admin/structure', 'Add category'), ('/admin/bans', 'No bans'), ('/admin/users', 'dialup_dan'),
                     ('/admin/modlog', 'Mod log')]:
    _, html = get(admin, path)
    print(path, '200:', marker in html)

# view-once per session, asserted on DB truth (page text races with live browsing)
_, html = get(admin, '/new-topic/2')
url, _ = post(admin, '/new-topic/2', {'csrf': csrf(html), 'title': 'V23 views probe', 'body': 'count me once'})
vtid = int(re.search(r'\.t(\d+)', url).group(1))
sA = session()
get(sA, url)
a = views_db(vtid)
get(sA, url)
b = views_db(vtid)
sB = session()
get(sB, url)
c = views_db(vtid)
print('views (db):', a, b, c, '-> once-per-session:', b == a and c == a + 1)

# profile editor as dialup_dan
mem = session()
login(mem, 'dialup_dan', 'password123')
_, ehtml = get(mem, '/settings/profile')
print('editor form:', 'Bio' in ehtml and 'Change password' in ehtml)
_, ehtml2 = post(mem, '/settings/profile', {'csrf': csrf(ehtml), 'form': 'profile', 'email': 'dan@example.com', 'bio': 'v23 test bio line'})
print('bio saved:', 'v23 test bio line' in ehtml2)
_, ehtml = get(mem, '/settings/profile')
_, ehtml2 = post(mem, '/settings/profile', {'csrf': csrf(ehtml), 'form': 'password', 'current': 'password123', 'new': 'newpass123'})
print('password changed:', 'Password changed' in ehtml2)
mem2 = session()
login(mem2, 'dialup_dan', 'newpass123')
_, home = get(mem2, '/')
print('new password works:', 'dialup_dan' in home)
_, ehtml = get(mem2, '/settings/profile')
post(mem2, '/settings/profile', {'csrf': csrf(ehtml), 'form': 'password', 'current': 'newpass123', 'new': 'password123'})
print('password restored')

# sitemap xsl follows theme cookie
dark = session()
dark.open(base + '/theme/dark')
xsl = dark.open(base + '/sitemap.xsl').read().decode()
print('xsl dark:', '#14161b' in xsl)
light = session()
light.open(base + '/theme/light')
xsl2 = light.open(base + '/sitemap.xsl').read().decode()
print('xsl light:', '#dfe3ee' in xsl2)
print('V23 TESTS DONE')
