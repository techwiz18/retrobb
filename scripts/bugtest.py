import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
base = 'http://localhost:8080'

def get(path):
    r = op.open(base + path)
    return r.url, r.read().decode()

def csrf(html):
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    return m.group(1) if m else None

def post(path, fields):
    data = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(base + path, data=data)
    r = op.open(req)
    return r.url, r.read().decode()

# login as admin
_, html = get('/login')
post('/login', {'csrf': csrf(html), 'login': 'admin', 'password': 'admin123', 'next': '/'})
print('== logged in as admin ==')

# 1. new thread form
url, html = get('/new-topic/2')
print('new-topic form 200:', 'New topic in' in html, '| url:', url)
print('new-topic error shown:', 'Session expired' in html or '404' in html)
tok = csrf(html)
print('form csrf present:', bool(tok))
if tok:
    try:
        url, html = post('/new-topic/2', {'csrf': tok, 'title': 'Bugtest topic please ignore', 'body': 'testing new thread creation'})
        print('new-topic submit final url:', url)
        print('new topic visible:', 'Bugtest topic please ignore' in html)
    except urllib.error.HTTPError as e:
        print('new-topic submit HTTPError:', e.code, e.read().decode()[:300])

# 2. pin toggle on topic 2 (bugs live here; find bugtest topic id from redirect above if created)
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None
op2 = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(cj))
def post_noredir(path, fields):
    data = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(base + path, data=data)
    try:
        r = op2.open(req)
        return r.code, r.headers.get('Location'), r.read().decode()[:200]
    except urllib.error.HTTPError as e:
        body = e.read().decode()[:300]
        return e.code, e.headers.get('Location'), body

_, html = get('/topic/what-was-your-first-forum.t2')
tok = csrf(html)
print('topic page csrf:', bool(tok))
code, loc, body = post_noredir('/topic/2/pinned', {'csrf': tok})
print('pin toggle:', code, '->', loc, '| body:', body.replace('\n', ' '))
_, html = get('/topic/what-was-your-first-forum.t2')
print('pinned now shows unpin:', 'Unpin' in html)
code, loc, body = post_noredir('/topic/2/pinned', {'csrf': csrf(html)})
print('unpin toggle:', code, '->', loc)
code, loc, body = post_noredir('/topic/2/locked', {'csrf': csrf(html)})
print('lock toggle:', code, '->', loc, '| body:', body.replace('\n', ' '))
_, html = get('/topic/what-was-your-first-forum.t2')
print('locked shows unlock:', 'Unlock' in html)
code, loc, body = post_noredir('/topic/2/locked', {'csrf': csrf(html)})
print('unlock toggle:', code, '->', loc)

# 3. sitemap
url, html = get('/sitemap.xml')
print('sitemap head:', html[:120].replace('\n', ' '))
