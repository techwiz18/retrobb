import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
base = 'http://localhost:8080'
PASS, FAIL = [], []

def check(name, cond, detail=''):
    (PASS if cond else FAIL).append(name)
    print(('PASS ' if cond else 'FAIL ') + name, detail)

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    url = path if path.startswith('http') else base + path
    try:
        r = op.open(url)
        return r.status, r.url, r.read().decode(), r.headers
    except urllib.error.HTTPError as e:
        return e.code, url, e.read().decode(), e.headers

def raw(op, method, path, fields=None):
    data = urllib.parse.urlencode(fields or {}, doseq=True).encode() if fields is not None else None
    req = urllib.request.Request(base + path, data=data, method=method)
    try:
        r = op.open(req)
        return r.status, r.read().decode(), r.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode(), e.headers

def csrf(html):
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    return m.group(1) if m else 'missing'

def login(op, u, p):
    _, _, html, _ = get(op, '/login')
    return raw(op, 'POST', '/login', {'csrf': csrf(html), 'login': u, 'password': p})

admin = session()
login(admin, 'admin', 'admin123')
# admin csrf + flood off for test throughput
_, _, html, _ = get(admin, '/admin/settings')
tokA = csrf(html)
raw(admin, 'POST', '/admin/settings', {'csrf': tokA, 'flood_seconds': '0', 'return': 'settings'})

mem = session()
_, _, html, _ = get(mem, '/register')
import random as _rnd
suffix = str(_rnd.randint(1000, 9999))
fields = {'csrf': csrf(html), 'username': 'vulnmember' + suffix, 'email': 'vuln%s@example.com' % suffix,
          'password': 'password123', 'website': ''}
qm = re.search(r'what is (\d+) \+ (\d+)', html)
if qm:
    fields['captcha_answer'] = str(int(qm.group(1)) + int(qm.group(2)))
_, _, html, _ = get(mem, '/register')
# refetch csrf (fresh page) then register
code, body, _ = raw(mem, 'POST', '/register', dict(fields, csrf=csrf(html)))
check('test member registered', 'Log out' in body, '')
vm = re.search(r'/members/([a-z0-9\-]+)\.u(\d+)', body)
vslug, vid = vm.group(1), vm.group(2)
vemail = 'vuln%s@example.com' % suffix
_, _, html, _ = get(mem, '/topic/what-was-your-first-forum.t2')
tokM = csrf(html)

print('--- A. ACCESS CONTROL as member ---')
# mod-only POSTs with valid member csrf -> must be 403
for name, path, fields in [
    ('pin as member', '/topic/2/pinned', {}),
    ('lock as member', '/topic/2/locked', {}),
    ('delete topic as member', '/topic/2/delete', {}),
    ('move as member', '/topic/2/move', {'forum_id': '1'}),
    ('merge as member', '/topic/2/merge', {'target': '1'}),
    ('handle report as member', '/mod/report/1/handle', {'status': 'resolved'}),
    ('warn as member', '/members/1/warn', {'reason': 'x' * 10}),
    ('ban as member', '/members/1/ban', {'reason': 'x' * 10, 'days': '1'}),
    ('setGroup as member', '/admin/user/2/group', {'group': 'admin'}),
    ('admin settings as member', '/admin/settings', {'board_name': 'PWNED'}),
]:
    f = dict(fields)
    f['csrf'] = tokM
    code, body, _ = raw(mem, 'POST', path, f)
    check('blocked: ' + name, code in (403, 419), 'got %s' % code)
# mod-only GETs
for name, path in [('mod queue as member', '/mod/reports'), ('admin page as member', '/admin/users'),
                   ('merge form as member', '/topic/2/merge')]:
    code, _, body, _ = get(mem, path)
    check('blocked: ' + name, code in (403,) or 'Forbidden' in body, 'got %s' % code)
# edit form for someone else's post: must bounce back to the topic, never render the form
code, curl, body, _ = get(mem, '/post/1/edit')
check('blocked: edit admin post as member', 'maintitle">Edit post' not in body, 'at %s' % curl)
# logged-out POST with no csrf -> 419/redirect-login, never applied
anon = session()
code, body, _ = raw(anon, 'POST', '/topic/2/pinned', {})
check('anon pin rejected', code in (403, 419), 'got %s' % code)
# board name unchanged after member attempt
_, _, html, _ = get(admin, '/')
check('board name intact', 'PWNED' not in html)

print('--- B. STORED XSS ---')
xss_posts = [
    '<script>alert(1)</script>',
    '<img src=x onerror=alert(2)>',
    '[url=javascript:alert(3)]click[/url]',
    '[url="x" onclick="alert(4)"]click[/url]',
    '[img]http://x"onerror="alert(5)[/img]',
    '[b]<script>alert(6)</script>[/b]',
]
payload = '\n'.join(xss_posts)
_, _, html, _ = get(mem, '/new-topic/3')
code, body, _ = raw(mem, 'POST', '/new-topic/3', {'csrf': csrf(html), 'title': '<script>alert(7)</script>', 'body': payload})
_, _, fhtml, _ = get(mem, '/forum/test-zone.f3')
links = re.findall(r'/topic/([^"]+)\.t(\d+)">([^<]*)</a>', fhtml)
ours = [l for l in links if 'script' in l[2]]
print('our xss topic link found:', bool(ours))
if ours:
    turl = '/topic/%s.t%s' % (ours[0][0], ours[0][1])
    _, _, thtml, _ = get(mem, turl)
    check('no <script> in rendered topic', '<script>' not in thtml)
    check('no javascript: href', 'href="javascript:' not in thtml.lower())
    check('event handlers escaped', ' onerror="' not in thtml and ' onclick="' not in thtml)
    check('title escaped', '&lt;script&gt;' in thtml)
else:
    check('xss topic created+found', False)
# bio XSS
_, _, html, _ = get(mem, '/settings/profile')
_ = raw(mem, 'POST', '/settings/profile', {'csrf': csrf(html), 'form': 'profile', 'email': vemail, 'bio': '</div><script>alert(9)</script>'})
_, _, phtml, _ = get(mem, '/members/%s.u%s' % (vslug, vid))
check('bio escaped', '<script>' not in phtml and '&lt;/div&gt;' in phtml)

print('--- C. SQLi probes ---')
_, _, html, _ = get(admin, '/topic/1/merge?q=%27%20OR%201%3D1--')
check('merge search no error dump', 'Fatal error' not in html and 'Exception' not in html)
code, _, body, _ = get(mem, '/topic/x.t999999')
check('missing topic 404 not 500', code == 404, 'got %s' % code)
code, _, body, _ = get(mem, "/topic/x.t1'OR'1'='1")
check('sqli slug 404', code == 404, 'got %s' % code)
code, curl, body, _ = get(mem, '/viewtopic.php?t=1%20OR%201%3D1')
check('legacy redirect safe', '/topic/' in curl and 'Fatal' not in body, 'at %s' % curl)
code, body, _ = raw(mem, 'POST', '/login', {'csrf': tokM, 'login': "' OR '1'='1", 'password': 'x', 'next': '/'})
check('sqli login fails cleanly', 'Invalid login' in body)

print('--- D. CSRF / redirects / session ---')
code, body, _ = raw(mem, 'GET', '/logout', {})
check('logout via GET does not log out', True)
_, _, html, _ = get(mem, '/')
check('still logged in after GET logout', 'Log out' in html)
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

def login_noredir(u, p, next_):
    jar = http.cookiejar.CookieJar()
    op2 = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(jar))
    html = op2.open(base + '/login').read().decode()
    data = urllib.parse.urlencode({'csrf': csrf(html), 'login': u, 'password': p}).encode()
    req = urllib.request.Request(base + '/login?next=' + next_, data=data)
    try:
        op2.open(req)
        return 200, None
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location')

code, loc = login_noredir('dialup_dan', 'password123', '%2F%2Fevil.com')
check('login next=//evil blocked', code == 302 and loc == '/', 'got %s -> %s' % (code, loc))
# session cookie flags
_, _, _, hdrs = get(session(), '/login')
sc = hdrs.get('Set-Cookie', '') or hdrs.get('set-cookie', '')
print('session cookie header:', sc[:120])
check('cookie HttpOnly', 'httponly' in sc.lower(), sc[:80])
# security headers on home
_, _, _, hdrs = get(session(), '/')
for h in ['X-Frame-Options', 'X-Content-Type-Options']:
    check('header ' + h, hdrs.get(h) is not None, str(hdrs.get(h)))
print('RESULT: %d passed, %d failed' % (len(PASS), len(FAIL)))
if FAIL:
    print('FAILED:', FAIL)
