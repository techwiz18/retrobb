import os, re, sys, time, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    url = path if path.startswith('http') else base + path
    r = op.open(url)
    return r.url, r.read().decode()

def post(op, path, fields):
    r = op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields, doseq=True).encode()))
    return r.url, r.read().decode()

def csrf(html):
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    return m.group(1) if m else None

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

def status(op, path):
    op2 = urllib.request.build_opener(NoRedir)
    try:
        r = op2.open(base + path)
        return r.code, r.headers.get('Location')
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location')

def login(op, u, p):
    _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

def find_report(html, reason):
    """The queue is shared state; find OUR report by its reason text."""
    for block in html.split('<div class="recent-row"')[1:]:
        if reason in block:
            m = re.search(r'/mod/report/(\d+)/', block)
            if m:
                return m.group(1)
    return None

admin = session()
login(admin, 'admin', 'admin123')
_, html = get(admin, '/admin')
post(admin, '/admin/settings', {'csrf': csrf(html), 'flood_seconds': '0'})

# case-insensitive register + login (unique name keeps repeat runs clean)
uname = 'V22Case%d' % int(time.time() % 100000)
m = session()
_, html = get(m, '/register')
_, r2 = post(m, '/register', {'csrf': csrf(html), 'username': uname, 'email': uname.lower() + '@example.com', 'password': 'password123', 'website': ''})
check('register mixed case', uname in r2)
m2 = session()
_, html = get(m2, '/login')
_, r3 = post(m2, '/login', {'csrf': csrf(html), 'login': uname.lower(), 'password': 'password123', 'next': '/'})
check('lowercase login works', uname in r3)
m3 = session()
_, html = get(m3, '/register')
_, r4 = post(m3, '/register', {'csrf': csrf(html), 'username': uname.upper(), 'email': 'other@example.com', 'password': 'password123', 'website': ''})
check('case-clash rejected', 'taken' in r4.lower())

# topic for move/ghost/merge tests
_, html = get(m, '/new-topic/2')
url, _ = post(m, '/new-topic/2', {'csrf': csrf(html), 'title': 'V22 ghost test', 'body': 'move me please'})
tid = re.search(r'\.t(\d+)', url).group(1)
_, mvhtml = get(admin, '/topic/' + tid + '/move')
post(admin, '/topic/' + tid + '/move', {'csrf': csrf(mvhtml), 'forum_id': '3', 'ghost': '1'})
_, fhtml = get(admin, '/forum/general-chat.f2')
gm = re.search(r'/topic/moved-[^"]*\.t(\d+)', fhtml)
gid = gm.group(1)
code, loc = status(admin, '/topic/moved-v22-ghost-test.t' + gid)
check('ghost 301s to new home', code == 301 and ('.t' + tid) in (loc or ''), '%s -> %s' % (code, loc))

# merge page friendlier
_, mghtml = get(admin, '/topic/' + tid + '/merge')
check('merge picker present', 'Search by title' in mghtml and 'type="radio"' in mghtml)
_, mghtml = get(admin, '/topic/' + tid + '/merge?q=welcome')
check('merge search finds topic', 'Welcome to RetroBB' in mghtml)
tgt = re.search(r'name="target" value="(\d+)"', mghtml).group(1)
urlm, _ = post(admin, '/topic/' + tid + '/merge', {'csrf': csrf(mghtml), 'target': tgt})
check('merge via picker', '.t' + tgt in urlm)

# queue actions: report a member's post, then warn-author resolves it (the
# staff shields refuse warning staff/self, so target a plain member)
_, html = get(m, '/topic/bbcode-test-thread.t3')
pm = re.search(r'/post/(\d+)/report', html).group(1)
_, rh = get(m, '/post/' + pm + '/report')
post(m, '/post/' + pm + '/report', {'csrf': csrf(rh), 'reason': 'v22 warn test'})
_, qh = get(admin, '/mod/reports')
rid = find_report(qh, 'v22 warn test')
check('own report appears in queue', rid is not None)
post(admin, '/mod/report/' + rid + '/warn-author', {'csrf': csrf(qh), 'reason': 'v22 warn reason'})
_, qh2 = get(admin, '/mod/reports')
check('warn-author resolves the report', 'v22 warn test' not in qh2)
warned = session()
login(warned, 'Guest_2003', 'password123')
_, ph2 = get(warned, '/members/guest-2003.u4')
check('warning visible on warned member profile', 'view reported post' in ph2)
_, home = get(warned, '/')
check('warning banner on warned member home', 'warning' in home.lower() and 'profile' in home.lower())

# second report -> delete lone post removes the topic
_, html = get(m, '/new-topic/3')
urlx, _ = post(m, '/new-topic/3', {'csrf': csrf(html), 'title': 'V22 delete me', 'body': 'doomed post'})
dtid = re.search(r'\.t(\d+)', urlx).group(1)
_, th = get(admin, '/topic/x.t' + dtid)
dpm = re.search(r'/post/(\d+)/report', th)
dpid = dpm.group(1)
_, rh = get(admin, '/post/' + dpid + '/report')
post(admin, '/post/' + dpid + '/report', {'csrf': csrf(rh), 'reason': 'v22 delete test'})
_, qh = get(admin, '/mod/reports')
rid2 = find_report(qh, 'v22 delete test')
post(admin, '/mod/report/' + rid2 + '/delete-post', {'csrf': csrf(qh)})
try:
    get(admin, '/topic/x.t' + dtid)
    gone = False
except urllib.error.HTTPError as e:
    gone = e.code == 404
check('delete-post removes lone-post topic', gone)

# profile links present
_, th = get(admin, '/topic/what-was-your-first-forum.t2')
check('profile links on posts', '/members/dialup-dan.u' in th)
finish()
