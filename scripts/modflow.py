"""Moderation flows. Canonical fixture: admin=1 (super), PixelMod=2 (mod),
dialup_dan=3 (member/target), Guest_2003=4 (member/reporter)."""
import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

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

def post(op, formpage, path, fields):
    """Fetch formpage for a CSRF token, then POST path. Returns (body, code)."""
    html = op.open(base + formpage).read().decode()
    fields['csrf'] = csrf(html)
    try:
        r = op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode()))
        return r.read().decode(), None
    except urllib.error.HTTPError as e:
        return '', e.code

admin, mod, reporter = session(), session(), session()
login(admin, 'admin', 'admin123')
login(mod, 'PixelMod', 'password123')
login(reporter, 'Guest_2003', 'password123')

# report a member's post (topic 2 is dialup_dan's; avoid reporting staff, which
# the staff shields block).
thtml = reporter.open(base + '/topic/what-was-your-first-forum.t2').read().decode()
pid = re.search(r'/post/(\d+)/report', thtml).group(1)
_, code = post(reporter, f'/post/{pid}/report', f'/post/{pid}/report', {'reason': 'modflow test report'})
check('member can report a post', code is None, code)
_, code2 = post(reporter, f'/post/{pid}/report', f'/post/{pid}/report', {'reason': 'second try'})
note('duplicate report code: %s' % code2)
_, code3 = post(admin, f'/post/{pid}/report', f'/post/{pid}/report', {'reason': 'admin view'})
check('admin can report same post', code3 is None, code3)

q = mod.open(base + '/mod/reports').read().decode()
check('queue shows report reason', 'modflow test report' in q)
ridm = re.search(r'/mod/report/(\d+)/resolve', q)
rid = ridm.group(1) if ridm else None
check('open report appears in queue', rid is not None)

if rid:
    b, _ = post(mod, f'/mod/report/{rid}/resolve', f'/mod/report/{rid}/handle', {'status': 'resolved', 'note': ''})
    check('resolve without note bounces', 'what action you took' in b)
    w, _ = post(mod, f'/mod/report/{rid}/warn', f'/mod/report/{rid}/warn-author', {'reason': 'modflow warning'})
    check('warn-author from queue warns the author', 'Author warned' in w)

# direct profile warn + ban of a member
prof = mod.open(base + '/members/dialup-dan.u3').read().decode()
check('profile shows member', 'dialup_dan' in prof)
_, bwc = post(mod, '/members/dialup-dan.u3', '/members/3/warn', {'reason': 'direct modflow warn'})
check('mod can warn a member directly', bwc is None, bwc)
_, bbc = post(mod, '/members/dialup-dan.u3', '/members/3/ban', {'reason': 'modflow temp ban', 'days': '1'})
check('mod can temp-ban a member', bbc is None, bbc)

# a banned member cannot log in, and sees why
blocked = session()
html = blocked.open(base + '/login').read().decode()
resp = blocked.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
    {'csrf': csrf(html), 'login': 'dialup_dan', 'password': 'password123'}).encode())).read().decode()
check('banned login shows reason', 'banned' in resp.lower())

# unban, then the member can log in again
bans = mod.open(base + '/mod/bans').read().decode()
m = re.search(r'/members/unban/(\d+)', bans)
check('ban listed in ModCP', m is not None)
_, ubc = post(mod, '/members/dialup-dan.u3', '/members/unban/' + (m.group(1) if m else '1'), {})
check('mod can unban', ubc is None, ubc)
back = session()
login(back, 'dialup_dan', 'password123')
check('member can log in after unban', 'dialup_dan' in back.open(base + '/').read().decode())

ml = admin.open(base + '/admin/modlog').read().decode()
check('modlog has warn', 'warn' in ml.lower())
check('modlog has ban', 'ban' in ml.lower())
check('modlog has unban', 'unban' in ml.lower())

# --- shared refusal seam (issue #37) -------------------------------------
def raw(op, path, fields, referer=None):
    """POST without following the outcome away; returns (body, code)."""
    req = urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode())
    if referer:
        req.add_header('Referer', base + referer)
    try:
        r = op.open(req)
        return r.read().decode(), r.status
    except urllib.error.HTTPError as e:
        return e.read().decode(), e.code

def chrome(html):
    return '<nav' in html and 'class="footer"' in html

topic1 = mod.open(base + '/topic/welcome-to-retrobb.t1').read().decode()
tid = re.search(r'/topic/(\d+)/pinned', topic1).group(1)
staff_pid = None
for pid, who in re.findall(r'id="p(\d+)" data-username="([^"]+)"', topic1):
    if who == 'admin':
        staff_pid = pid
        break
check('found a post by another staffer', staff_pid is not None)

# A mod may not touch another staffer's post: one error page, not a bounce.
body, code = raw(mod, f'/post/{staff_pid}/delete', {'csrf': csrf(topic1)})
check('staff-post refusal is the 403 page', code == 403, code)
check('staff-post refusal keeps board chrome', chrome(body))
check('staff-post refusal says why', 'Only super admins' in body)
check('staff-post refusal is not a bare string', body.strip() != 'Forbidden')
check('staff post survived the refusal',
      f'id="p{staff_pid}"' in mod.open(base + '/topic/welcome-to-retrobb.t1').read().decode())

# A stale token renders the retry page with board chrome, never a bare 419.
body, code = raw(mod, f'/topic/{tid}/pinned', {'csrf': 'stale'})
check('topic csrf refusal is a rendered page', code != 419 and 'CSRF mismatch' not in body, code)
check('topic csrf refusal keeps board chrome', chrome(body))
check('topic csrf refusal explains itself', 'session expired' in body.lower(), body[:80])

# Members never reach a staff action, and still get the board's own page.
body, code = raw(reporter, f'/topic/{tid}/pinned', {'csrf': csrf(thtml)})
check('member topic action is the 403 page', code == 403 and chrome(body), code)
check('member topic action is not a bare string', body.strip() != 'Forbidden')

# A missing topic is the shared 404 page with chrome.
try:
    reporter.open(base + '/topic/nope.t999999')
    code, body = 200, ''
except urllib.error.HTTPError as e:
    code, body = e.code, e.read().decode()
check('missing topic is the shared 404 page', code == 404 and chrome(body), code)

try:
    reporter.open(base + '/mod/reports')
    check('member cannot reach mod queue', False, 'reached queue')
except urllib.error.HTTPError as e:
    check('member cannot reach mod queue', e.code == 403, e.code)

# Leave the shared queue empty for whichever suite runs next (order-independence).
for _ in range(10):
    q = mod.open(base + '/mod/reports').read().decode()
    ids = re.findall(r'/mod/report/(\d+)/handle', q)
    if not ids:
        break
    post(mod, '/mod/reports', '/mod/report/%s/handle' % ids[0], {'status': 'dismissed', 'note': ''})
check('queue left clean', 'Queue is clear' in mod.open(base + '/mod/reports').read().decode())
finish()
