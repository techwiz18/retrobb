import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    url = path if path.startswith('http') else base + path
    try:
        r = op.open(url)
        return r.status, r.url, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, url, e.read().decode()

def post(op, path, fields):
    r = op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields, doseq=True).encode()))
    return r.url, r.read().decode()

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    _, _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

admin = session()
login(admin, 'admin', 'admin123')
mod = session()
login(mod, 'PixelMod', 'password123')

# 1. demoting a mod revokes powers mid-session, restoring brings them back
code, _, _ = get(mod, '/mod/reports')
check('mod reaches queue before demote', code == 200, code)
_, _, html = get(admin, '/admin/users')
post(admin, '/admin/user/2/group', {'csrf': csrf(html), 'group': 'member'})
code, _, _ = get(mod, '/mod/reports')
check('demoted mod loses queue', code == 403, code)
_, _, html = get(admin, '/admin/users')
post(admin, '/admin/user/2/group', {'csrf': csrf(html), 'group': 'mod'})
code, _, _ = get(mod, '/mod/reports')
check('restored mod regains queue', code == 200, code)

# 2. ban while online boots the session
victim = session()
login(victim, 'dialup_dan', 'password123')
_, _, html = get(victim, '/')
check('victim logged in', 'dialup_dan' in html)
_, _, html = get(admin, '/members/dialup-dan.u3')
post(admin, '/members/3/ban', {'csrf': csrf(html), 'reason': 'session test', 'days': '1'})
_, _, html = get(victim, '/')
check('banned user booted mid-session', 'dialup_dan' not in html and 'Log in' in html)
check('ban reason shown', 'banned' in html.lower())
# unban, confirm login works again
_, _, html = get(admin, '/admin/bans')
m = re.search(r'/admin/unban/(\d+)', html)
post(admin, '/admin/unban/' + m.group(1), {'csrf': csrf(html)})
login(victim, 'dialup_dan', 'password123')
_, _, html = get(victim, '/')
check('login works after unban', 'dialup_dan' in html)
finish()
