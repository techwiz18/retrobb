"""Super-admin tier checks. Canonical fixture ids: admin=1 (super),
PixelMod=2 (mod), dialup_dan=3 (member, the promoted one)."""
import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    html = op.open(base + '/login').read().decode()
    op.open(urllib.request.Request(base + '/login',
        data=urllib.parse.urlencode({'csrf': csrf(html), 'login': u, 'password': p}).encode()))

def get(op, path):
    try:
        return op.open(base + path).read().decode(), 200
    except urllib.error.HTTPError as e:
        return '', e.code

def post(op, formpage, path, fields):
    html = op.open(base + formpage).read().decode()
    fields['csrf'] = csrf(html)
    try:
        return op.open(urllib.request.Request(base + path,
            data=urllib.parse.urlencode(fields).encode())).read().decode(), 200
    except urllib.error.HTTPError as e:
        return '', e.code

super_s, admin2 = session(), session()
login(super_s, 'admin', 'admin123')
# promote a member to a NON-super admin so the tier gates are testable
post(super_s, '/admin/users', '/admin/user/3/group', {'group': 'admin'})
login(admin2, 'dialup_dan', 'password123')

_, c1 = get(admin2, '/admin/settings')
check('non-super admin blocked from settings', c1 == 403, c1)
_, c2 = get(admin2, '/admin/import')
check('non-super admin blocked from import', c2 == 403, c2)
_, c3 = post(admin2, '/admin/users', '/admin/user/2/group', {'group': 'member'})
check('non-super admin cannot set groups', c3 == 403, c3)
u, _ = get(admin2, '/admin/users')
check('users page hides super controls', 'Make super' not in u and 'name="group"' not in u)
d, _ = get(admin2, '/admin')
check('dashboard hides super cards', 'Run database updates' not in d and 'Import' not in d)
q, _ = get(admin2, '/mod/reports')
check('non-super admin keeps mod queue', 'Mod queue' in q)

# staff shields: only a super may warn/ban a mod
w, _ = post(admin2, '/members/pixelmod.u2', '/members/2/warn', {'reason': 'admin2 warn mod'})
check('non-super admin cannot warn a mod', 'super admins can warn staff' in w.lower())
_, wc = post(super_s, '/members/pixelmod.u2', '/members/2/warn', {'reason': 'super warn mod ok'})
check('super can warn a mod', wc == 200, wc)
bw, _ = post(admin2, '/members/pixelmod.u2', '/members/2/ban', {'reason': 'admin2 ban mod', 'days': '1'})
check('non-super admin cannot ban a mod', 'super admins can ban staff' in bw.lower())

# grant super + revoke + self-protection
_, gc = post(super_s, '/admin/users', '/admin/user/3/super', {})
check('super grants super', gc == 200, gc)
s, sc = get(admin2, '/admin/settings')
check('newly-super admin reaches settings', sc == 200 and 'Board name' in s, sc)
_, rc = post(super_s, '/admin/users', '/admin/user/3/super', {})
check('super revokes super', rc == 200, rc)
_, sc2 = get(admin2, '/admin/settings')
check('revoked admin blocked again', sc2 == 403, sc2)
post(super_s, '/admin/users', '/admin/user/1/super', {})
check('self super-revoke blocked', 'Board name' in super_s.open(base + '/admin/settings').read().decode())

# clean up the promoted admin
post(super_s, '/admin/users', '/admin/user/3/group', {'group': 'member'})
finish()
