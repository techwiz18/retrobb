import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(path):
    r = op.open(base + path)
    return r.url, r.read().decode()

def csrf(html):
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    return m.group(1) if m else None

def post(path, fields):
    r = op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode()))
    return r.url, r.read().decode()

_, html = get('/login')
post('/login', {'csrf': csrf(html), 'login': 'admin', 'password': 'admin123', 'next': '/'})

# 1. new-topic form + submit
url, html = get('/new-topic/2')
check('new-topic form renders', 'New topic in' in html)
tok = csrf(html)
check('new-topic form csrf present', bool(tok))
if tok:
    url, html = post('/new-topic/2', {'csrf': tok, 'title': 'Bugtest topic please ignore', 'body': 'testing new thread creation'})
    check('new topic visible', 'Bugtest topic please ignore' in html)

# 2. pin / lock toggles on the fixture topic
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None
op2 = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(cj))

def post_noredir(path, fields):
    try:
        r = op2.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode()))
        return r.code, r.headers.get('Location')
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location')

_, html = get('/topic/what-was-your-first-forum.t2')
tok = csrf(html)
check('topic page csrf present', bool(tok))

code, loc = post_noredir('/topic/2/pinned', {'csrf': tok})
check('pin toggle redirects to topic', code == 302 and '/topic/' in (loc or ''), '%s -> %s' % (code, loc))
check('pinned shows unpin', 'Unpin' in get('/topic/what-was-your-first-forum.t2')[1])
_, html = get('/topic/what-was-your-first-forum.t2')
post_noredir('/topic/2/pinned', {'csrf': csrf(html)})

_, html = get('/topic/what-was-your-first-forum.t2')
post_noredir('/topic/2/locked', {'csrf': csrf(html)})
check('locked shows unlock', 'Unlock' in get('/topic/what-was-your-first-forum.t2')[1])
_, html = get('/topic/what-was-your-first-forum.t2')
post_noredir('/topic/2/locked', {'csrf': csrf(html)})

# 3. sitemap is XML
_, html = get('/sitemap.xml')
check('sitemap is xml', html.lstrip().startswith('<?xml'))
finish()
