import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

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
    data = urllib.parse.urlencode(fields, doseq=True).encode()
    req = urllib.request.Request(base + path, data=data)
    r = op.open(req)
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

# 1. mod can reach queue now
code, _, _ = get(mod, '/mod/reports')
print('mod queue before demote:', code)
# admin demotes to member
_, _, html = get(admin, '/admin/users')
post(admin, '/admin/user/2/group', {'csrf': csrf(html), 'group': 'member'})
code, _, _ = get(mod, '/mod/reports')
print('mod queue right after demote:', code, '-> stale powers gone:', code == 403)
# restore
_, _, html = get(admin, '/admin/users')
post(admin, '/admin/user/3/group', {'csrf': csrf(html), 'group': 'mod'})
code, _, _ = get(mod, '/mod/reports')
print('mod queue after restore:', code)

# 2. ban while online
victim = session()
login(victim, 'dialup_dan', 'password123')
_, _, html = get(victim, '/')
print('victim logged in:', 'dialup_dan' in html)
_, _, html = get(admin, '/members/dialup-dan.u3')
post(admin, '/members/3/ban', {'csrf': csrf(html), 'reason': 'session test', 'days': '1'})
_, _, html = get(victim, '/')
print('banned user booted mid-session:', 'dialup_dan' not in html and 'Log in' in html)
print('ban reason shown:', 'banned' in html.lower())
# unban, login works
_, _, html = get(admin, '/admin/bans')
m = re.search(r'/admin/unban/(\d+)', html)
post(admin, '/admin/unban/' + m.group(1), {'csrf': csrf(html)})
login(victim, 'dialup_dan', 'password123')
_, _, html = get(victim, '/')
print('login works after unban:', 'dialup_dan' in html)
print('SESSION TESTS DONE')
