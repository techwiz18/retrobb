"""Temp: exercise moderation flows on wipe (backup/restore around it)."""
import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    html = op.open(base + '/login').read().decode()
    data = urllib.parse.urlencode({'csrf': csrf(html), 'login': u, 'password': p}).encode()
    op.open(urllib.request.Request(base + '/login', data=data))

def post(op, path, fields):
    html = op.open(base + path).read().decode() if fields.pop('_getform', None) else None
    if html is None:
        # fetch a page carrying a csrf token first
        html = op.open(base + fields.pop('_formpage')).read().decode()
    fields['csrf'] = csrf(html)
    req = urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode())
    try:
        return op.open(req).read().decode(), None
    except urllib.error.HTTPError as e:
        return '', e.code

admin, mod, member = session(), session(), session()
login(admin, 'admin', 'admin123')
login(mod, 'PixelMod', 'password123')
login(member, 'TechWiz18', 'password123')

# 1. member reports post 1 (need a real post id: parse topic page)
html = member.open(base + '/topic/welcome-to-retrobb-v0-3-test.t1').read().decode()
m = re.search(r'/post/(\d+)/report', html)
pid = m.group(1) if m else '1'
print('reporting post', pid)
body, code = post(member, f'/post/{pid}/report', {'_formpage': f'/post/{pid}/report', 'reason': 'modflow test report'})
print('report submit ok:', code is None)

# duplicate report should be rejected-ish (same reporter+post)
body2, code2 = post(member, f'/post/{pid}/report', {'_formpage': f'/post/{pid}/report', 'reason': 'second try'})
print('dup report code:', code2)

# self-report guard: admin reports own post? find admin post or skip (report other post as admin)
body3, code3 = post(admin, f'/post/{pid}/report', {'_formpage': f'/post/{pid}/report', 'reason': 'admin view'})
print('admin second reporter ok:', code3 is None)

# 2. queue visible to mod with the report
q = mod.open(base + '/mod/reports').read().decode()
print('queue shows reason:', 'modflow test report' in q)
rid = re.search(r'/mod/report/(\d+)/resolve', q)
rid = rid.group(1) if rid else None
print('open report id:', rid)

# 3. resolve without note should bounce (note required)
if rid:
    b, c = post(mod, f'/mod/report/{rid}/handle', {'_formpage': f'/mod/report/{rid}/resolve', 'status': 'resolved', 'note': ''})
    print('resolve-no-note bounced:', 'what action you took' in b)

# 4. warn flow via queue (member TechWiz18 gets warning)
if rid:
    w, wc = post(mod, f'/mod/report/{rid}/warn-author', {'_formpage': f'/mod/report/{rid}/warn', 'reason': 'modflow warning'})
    print('warn-author code:', wc)

# 5. direct profile warn + ban
prof = mod.open(base + '/members/techwiz18.u4').read().decode()
print('profile shows member:', 'TechWiz18' in prof)
bw, bwc = post(mod, '/members/4/warn', {'_formpage': '/members/techwiz18.u4', 'reason': 'direct modflow warn'})
print('direct warn code:', bwc)
bb, bbc = post(mod, '/members/4/ban', {'_formpage': '/members/techwiz18.u4', 'reason': 'modflow temp ban', 'days': '1'})
print('temp ban code:', bbc)

# 6. banned member login blocked (ban message on the login page)
m2 = session()
html = m2.open(base + '/login').read().decode()
data = urllib.parse.urlencode({'csrf': csrf(html), 'login': 'TechWiz18', 'password': 'password123'}).encode()
resp = m2.open(urllib.request.Request(base + '/login', data=data)).read().decode()
print('banned login shows reason:', 'banned' in resp.lower())

# 7. unban by ban id (mod path lands on profile, admin path on bans list)
ub, ubc = post(mod, '/members/unban/1', {'_formpage': '/members/techwiz18.u4'})
print('mod unban code:', ubc)
ml = admin.open(base + '/admin/modlog').read().decode()
print('modlog has warn:', 'warn' in ml.lower(), '| has ban:', 'ban' in ml.lower(), '| has unban:', 'unban' in ml.lower())

# 8. member cannot reach queue
try:
    member.open(base + '/mod/reports')
    print('member queue: OPEN (bad)')
except urllib.error.HTTPError as e:
    print('member queue:', e.code, '(want 403)')
