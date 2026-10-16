"""Temp: super-admin tier checks."""
import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
base = 'http://localhost:8080'

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    html = op.open(base + '/login').read().decode()
    data = urllib.parse.urlencode({'csrf': csrf(html), 'login': u, 'password': p}).encode()
    op.open(urllib.request.Request(base + '/login', data=data))

def get(op, path):
    try:
        return op.open(base + path).read().decode(), 200
    except urllib.error.HTTPError as e:
        return '', e.code

def post(op, formpage, path, fields):
    html = op.open(base + formpage).read().decode()
    fields['csrf'] = csrf(html)
    try:
        return op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode())).read().decode(), 200
    except urllib.error.HTTPError as e:
        return '', e.code

super_s, admin2, mod = session(), session(), session()
login(super_s, 'admin', 'admin123')
# promote TechWiz18 (id 4) to admin (non-super) via super
b, c = post(super_s, '/admin/users', '/admin/user/4/group', {'group': 'admin'})
print('promote code:', c)
login(admin2, 'TechWiz18', 'password123')

# regular admin gates
_, c1 = get(admin2, '/admin/settings'); print('admin settings page:', c1, '(want 403)')
_, c2 = get(admin2, '/admin/import'); print('admin import page:', c2, '(want 403)')
_, c3 = post(admin2, '/admin/users', '/admin/user/18/group', {'group': 'member'})
print('admin setGroup:', c3, '(want 403)')
u, _ = get(admin2, '/admin/users')
print('users hides set forms:', 'Make super' not in u and 'name="group"' not in u)
d, _ = get(admin2, '/admin')
print('dashboard hides super cards:', 'Run database updates' not in d and 'Import' not in d)
q, _ = get(admin2, '/mod/reports'); print('admin keeps queue:', 'Mod queue' in q)

# staff shields
w, _ = post(admin2, '/members/pixelmod.u18', '/members/18/warn', {'reason': 'admin2 warn mod'})
print('admin warns mod refused:', 'super admins can warn staff' in w.lower())
w2, _ = post(super_s, '/members/pixelmod.u18', '/members/18/warn', {'reason': 'super warn mod ok'})
print('super warns mod allowed:', w2 is not None)
bw, _ = post(admin2, '/members/pixelmod.u18', '/members/18/ban', {'reason': 'admin2 ban mod', 'days': '1'})
print('admin bans mod refused:', 'super admins can ban staff' in bw.lower())

# super grant + revoke + self-protection
g, gc = post(super_s, '/admin/users', '/admin/user/4/super', {})
print('grant super code:', gc)
s, _ = get(admin2, '/admin/settings'); print('newly-super settings:', s)
r, rc = post(super_s, '/admin/users', '/admin/user/4/super', {})
print('revoke super code:', rc)
s2, sc2 = get(admin2, '/admin/settings'); print('revoked settings:', sc2, '(want 403)')
selfdemo, sdc = post(super_s, '/admin/users', '/admin/user/1/super', {})
print('self super-revoke blocked (still super):', 'settings' in super_s.open(base + '/admin/settings').read().decode())

# demote back to member for clean state
post(super_s, '/admin/users', '/admin/user/4/group', {'group': 'member'})
print('demoted back')
