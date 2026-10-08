import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
base = 'http://localhost:8080'

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

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

def status(op, path):
    op2 = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(op._cookies if hasattr(op, '_cookies') else http.cookiejar.CookieJar()))
    try:
        r = op2.open(base + path)
        return r.code, r.headers.get('Location')
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location')

def login(op, u, p):
    _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

login(admin, 'admin', 'admin123')
_, html = get(admin, '/admin')
post(admin, '/admin/settings', {'csrf': csrf(html), 'flood_seconds': '0'})
print('setup ok')

# case-insensitive register + login
m = session()
_, html = get(m, '/register')
_, r2 = post(m, '/register', {'csrf': csrf(html), 'username': 'V2Case', 'email': 'v2case@example.com', 'password': 'password123', 'website': ''})
print('register mixed case:', 'V2Case' in r2)
m2 = session()
_, html = get(m2, '/login')
_, r3 = post(m2, '/login', {'csrf': csrf(html), 'login': 'v2case', 'password': 'password123', 'next': '/'})
print('lowercase login works:', 'V2Case' in r3)
m3 = session()
_, html = get(m3, '/register')
_, r4 = post(m3, '/register', {'csrf': csrf(html), 'username': 'V2CASE', 'email': 'other@example.com', 'password': 'password123', 'website': ''})
print('case-clash rejected:', 'taken' in r4.lower())

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
print('ghost 301s to new home:', code == 301 and ('.t' + tid) in (loc or ''))

# merge page friendlier
_, mghtml = get(admin, '/topic/' + tid + '/merge')
print('merge picker:', 'Search by title' in mghtml and 'type="radio"' in mghtml)
_, mghtml = get(admin, '/topic/' + tid + '/merge?q=welcome')
print('merge search:', 'Welcome to RetroBB' in mghtml)
tgt = re.search(r'name="target" value="(\d+)"', mghtml).group(1)
urlm, _ = post(admin, '/topic/' + tid + '/merge', {'csrf': csrf(mghtml), 'target': tgt})
print('merge via picker:', '.t' + tgt in urlm)

# queue actions: report then warn-author, then report + delete-post
_, html = get(m, '/topic/welcome-to-retrobb-read-this-first.t1')
pm = re.search(r'/post/(\d+)/report', html).group(1)
_, rh = get(m, '/post/' + pm + '/report')
post(m, '/post/' + pm + '/report', {'csrf': csrf(rh), 'reason': 'v22 warn test'})
_, qh = get(admin, '/mod/reports')
rm = re.search(r'/mod/report/(\d+)/warn-author', qh)
rid = re.search(r'/mod/report/(\d+)/handle', qh).group(1)
post(admin, '/mod/report/' + rid + '/warn-author', {'csrf': csrf(qh)})
_, qh2 = get(admin, '/mod/reports')
print('warn-author resolves:', 'Queue is clear' in qh2)
_, ph = get(admin, '/members')
vm = re.search(r'/members/v2case\.u(\d+)', ph)
_, ph2 = get(admin, '/members/admin.u1')
print('warning visible on profile:', 'Reported post' in ph2)
# banner for warned user (admin got warned via warn-author)
_, home = get(admin, '/')
print('warning banner shown:', 'warning' in home.lower() and 'profile' in home.lower())
# second report -> delete post
_, html = get(m, '/new-topic/3')
urlx, _ = post(m, '/new-topic/3', {'csrf': csrf(html), 'title': 'V22 delete me', 'body': 'doomed post'})
dtid = re.search(r'\.t(\d+)', urlx).group(1)
_, th = get(admin, '/topic/x.t' + dtid)
dpm = re.search(r'/post/(\d+)/report', th)
if dpm:
    dpid = dpm.group(1)
    # admin reports it (authors can't report their own posts)
    _, rh = get(admin, '/post/' + dpid + '/report')
    post(admin, '/post/' + dpid + '/report', {'csrf': csrf(rh), 'reason': 'v22 delete test'})
    _, qh = get(admin, '/mod/reports')
    rid2 = re.search(r'/mod/report/(\d+)/handle', qh).group(1)
    post(admin, '/mod/report/' + rid2 + '/delete-post', {'csrf': csrf(qh)})
    try:
        get(admin, '/topic/x.t' + dtid)
        print('delete-post: topic still there (BAD unless had other posts)')
    except urllib.error.HTTPError as e:
        print('delete-post removed lone-post topic:', e.code == 404)
# profile links present
_, th = get(admin, '/topic/what-was-your-first-forum.t2')
print('profile links on posts:', '/members/dialup-dan.u' in th)
print('V22 TESTS DONE')
