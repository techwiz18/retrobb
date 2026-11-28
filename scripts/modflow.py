"""Moderation flows. Canonical fixture: admin=1 (super), PixelMod=2 (mod),
dialup_dan=3 (member/target), Guest_2003=4 (member/reporter)."""
import os, re, subprocess, sys, time, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.environ.get('RETROBB_TEST_DB', 'retrobb_test')

def sql(q):
    r = subprocess.run(['sh', 'scripts/mysql-sql.sh', q], capture_output=True, text=True,
                       cwd=ROOT, env=dict(os.environ, RETROBB_TEST_DB=DB))
    return r.stdout.strip()

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

noredir = urllib.request.build_opener(NoRedir)

def status(path):
    """HTTP status for an emitted link, without following a redirect."""
    try:
        noredir.open(base + path)
        return 200
    except urllib.error.HTTPError as e:
        return e.code

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
        return e.read().decode(), e.code

admin, mod, reporter = session(), session(), session()
login(admin, 'admin', 'admin123')
login(mod, 'PixelMod', 'password123')
login(reporter, 'Guest_2003', 'password123')

# report a member's post (topic 2 is dialup_dan's; avoid reporting staff, which
# the staff shields block).
thtml = reporter.open(base + '/topic/what-was-your-first-forum.t2').read().decode()
pid = re.search(r'/post/(\d+)/report', thtml).group(1)
# Reports carry a unique (post, reporter, status) key. A previous run leaves a
# dismissed row for the same fixed fixture post, so clear our own rows first
# (this suite must be re-runnable without a DB rebuild).
sql("DELETE FROM reports WHERE post_id=%s AND reporter_id IN "
    "(SELECT id FROM users WHERE username IN ('Guest_2003','admin'))" % pid)
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
qm = re.search(r'href="(/topic/[^"#]+\.t\d+)#p\d+"', q)
check('queue topic link is canonical', bool(qm) and status(qm.group(1)) == 200,
      '%s -> %s' % (qm.group(1), status(qm.group(1))) if qm else 'missing')

if rid:
    b, _ = post(mod, f'/mod/report/{rid}/resolve', f'/mod/report/{rid}/handle', {'status': 'resolved', 'note': ''})
    check('resolve without note bounces', 'what action you took' in b)
    w, _ = post(mod, f'/mod/report/{rid}/warn', f'/mod/report/{rid}/warn-author', {'reason': 'modflow warning'})
    check('warn-author from queue warns the author', 'Author warned' in w)

# the warning's context link must be the canonical topic URL, not a placeholder
prof = mod.open(base + '/members/dialup-dan.u3').read().decode()
wm = re.search(r'<a href="(/topic/[^"#]+\.t\d+(?:#p\d+)?)">view reported post</a>', prof)
check('warning context link is canonical',
      bool(wm) and status(wm.group(1).split('#')[0]) == 200,
      '%s -> %s' % (wm.group(1), status(wm.group(1).split('#')[0])) if wm else 'missing')

# Profile Warn/Ban forms follow the target policy (issue #35): a member is
# actionable, an admin never is.
prof = mod.open(base + '/members/dialup-dan.u3').read().decode()
check('mod sees Warn/Ban on a member profile', '/members/3/warn' in prof and '/members/3/ban' in prof)
adminprof = mod.open(base + '/members/admin.u1').read().decode()
check('mod sees no Warn/Ban on an admin profile',
      '/members/1/warn' not in adminprof and '/members/1/ban' not in adminprof)

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

# --- shared post-action policy (issue #34) -------------------------------
# Buttons and endpoints must agree: inside/expired windows, locked topics,
# opening posts with replies, staff posts. Fixtures are written straight to the
# test DB (deterministic created_at/locked) and removed through the topic
# delete endpoint so forum/user counters stay consistent.
stamp = str(int(time.time()))
now = time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime())
OLD = '2000-01-01 00:00:00'

def mk_post(tid, uid, created):
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) "
        "VALUES (%d,%d,'fixture','<p>fixture</p>','%s')" % (tid, uid, created))
    sql("UPDATE users SET posts_count=posts_count+1 WHERE id=%d" % uid)
    return int(sql("SELECT MAX(id) FROM posts WHERE topic_id=%d" % tid))

def mk_topic(uid, title, created, locked=0):
    slug = title.lower().replace(' ', '-')
    sql("INSERT INTO topics (forum_id,user_id,title,slug,views,posts_count,locked,created_at,last_post_at,last_post_user_id) "
        "VALUES (2,%d,'%s','%s',0,1,%d,'%s','%s',%d)" % (uid, title, slug, locked, created, created, uid))
    tid = int(sql("SELECT id FROM topics WHERE title='%s'" % title))
    pid = mk_post(tid, uid, created)
    return base + '/topic/%s.t%d' % (slug, tid), tid, pid

def seg(html, pid):
    start = html.find('id="p%s"' % pid)
    nxt = html.find('id="p', start + 10)
    return html[start:nxt if nxt != -1 else len(html)]

def can(html, pid, verb):
    return ('/post/%s/%s' % (pid, verb)) in seg(html, pid)

def blocks(html):
    return re.findall(r'id="p(\d+)" data-username="([^"]*)"', html)

# inside the window: author sees both actions
a_url, a_tid, a_pid = mk_topic(4, 'Policy window ' + stamp, now)
a_html = session()
login(a_html, 'Guest_2003', 'password123')
html = a_html.open(a_url).read().decode()
check('author sees Edit inside window', can(html, a_pid, 'edit'))
check('author sees Delete inside window', can(html, a_pid, 'delete'))

# after the window: neither action
b_url, b_tid, b_pid = mk_topic(4, 'Policy expired ' + stamp, OLD)
html = a_html.open(b_url).read().decode()
check('author sees no Edit after window', not can(html, b_pid, 'edit'))
check('author sees no Delete after window', not can(html, b_pid, 'delete'))

# locked: no author delete, moderator still deletes (edit stays open)
c_url, c_tid, c_pid = mk_topic(4, 'Policy locked ' + stamp, now, locked=1)
html = a_html.open(c_url).read().decode()
check('author sees no Delete in locked topic', not can(html, c_pid, 'delete'))
check('author still sees Edit in locked topic', can(html, c_pid, 'edit'))
check('moderator sees Delete in locked topic', can(mod.open(c_url).read().decode(), c_pid, 'delete'))

# opening post with a reply: author cannot remove it, moderator can
d_url, d_tid, d_pid = mk_topic(4, 'Policy opening ' + stamp, now)
d_reply = mk_post(d_tid, 3, now)
sql("UPDATE topics SET posts_count=2 WHERE id=%d" % d_tid)
html = a_html.open(d_url).read().decode()
check('author sees no Delete on opening post with replies', not can(html, d_pid, 'delete'))
check('moderator sees Delete on opening post with replies', can(mod.open(d_url).read().decode(), d_pid, 'delete'))

# another staffer's post: non-super mod gets nothing, the author and a super can
slug1 = sql("SELECT slug FROM topics WHERE id=1")
t1 = base + '/topic/%s.t1' % slug1
mhtml = mod.open(t1).read().decode()
by_name = {u: int(i) for i, u in blocks(mhtml)}
admin_pid, staff_pid = by_name.get('admin'), by_name.get('PixelMod')
check('staff-post fixture present', admin_pid is not None and staff_pid is not None)
if admin_pid and staff_pid:
    check('non-super mod sees no Edit on staff post', not can(mhtml, admin_pid, 'edit'))
    check('non-super mod sees no Delete on staff post', not can(mhtml, admin_pid, 'delete'))
    check('staff author keeps Edit on own post', can(mhtml, staff_pid, 'edit'))
    check('staff author keeps Delete on own post', can(mhtml, staff_pid, 'delete'))
    ahtml = admin.open(t1).read().decode()
    check('super admin sees Edit on staff post', can(ahtml, staff_pid, 'edit'))
    check('super admin sees Delete on staff post', can(ahtml, staff_pid, 'delete'))

# tampered POSTs are refused with the message the policy would have shown
b, _ = post(a_html, a_url[len(base):], '/post/%s/delete' % d_pid, {})
check('tampered delete of opening post refused', 'The opening post can only be removed by a moderator.' in b)
b, _ = post(a_html, a_url[len(base):], '/post/%s/edit' % d_reply, {'body': 'tampered'})
check('tampered edit of another post refused', 'You can only edit your own posts.' in b)
b, _ = post(a_html, a_url[len(base):], '/post/%s/delete' % c_pid, {})
check('tampered delete in locked topic refused', 'This topic is locked.' in b)
b, _ = post(a_html, a_url[len(base):], '/post/%s/edit' % b_pid, {'body': 'tampered'})
check('tampered edit after window refused', 'The edit window (30 min) has expired.' in b)
b, _ = post(a_html, a_url[len(base):], '/post/%s/delete' % b_pid, {})
check('tampered delete after window refused', 'The delete window (30 min) has expired.' in b)
b, _ = post(mod, t1[len(base):], '/post/%s/edit' % admin_pid, {'body': 'tampered'})
check('tampered edit of staff post refused', 'Only super admins can edit staff posts.' in b)

# the topic template must not re-derive the policy
src = open(os.path.join(ROOT, 'views/topic/show.php')).read()
check('topic view reads no window settings', 'edit_window_mins' not in src and 'delete_window_mins' not in src)
check('topic view reads no group check', 'Auth::isMod' not in src)

for u, tid in [(a_url, a_tid), (b_url, b_tid), (c_url, c_tid), (d_url, d_tid)]:
    post(admin, u[len(base):], '/topic/%d/delete' % tid, {})

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

# A moderator reaching the shared queue via the AdminCP path gets chrome whose
# links they can open (issue #35): ModCP nav, no admin-only entries.
aq = mod.open(base + '/admin/reports').read().decode()
check('mod on the AdminCP queue gets ModCP nav', '/mod/bans' in aq)
check('mod on the AdminCP queue sees no admin-only nav',
      '/admin/structure' not in aq and '/admin/settings' not in aq)

# Leave the shared queue empty for whichever suite runs next (order-independence).
for _ in range(10):
    q = mod.open(base + '/mod/reports').read().decode()
    ids = re.findall(r'/mod/report/(\d+)/handle', q)
    if not ids:
        break
    post(mod, '/mod/reports', '/mod/report/%s/handle' % ids[0], {'status': 'dismissed', 'note': ''})
check('queue left clean', 'Queue is clear' in mod.open(base + '/mod/reports').read().decode())
finish()
