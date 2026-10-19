import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

html = op.open(base + '/login').read().decode()
m = re.search(r'name="csrf" value="([^"]+)"', html)
check('login form csrf present', bool(m))
op.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
    {'csrf': m.group(1), 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()))
admin = op.open(base + '/admin').read().decode()
check('admin page renders AdminCP + Settings', 'AdminCP' in admin and 'Settings' in admin)

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None
op2 = urllib.request.build_opener(NoRedir)

# discover a live topic (ids/slugs are fixture-defined but discover anyway)
home = op.open(base + '/').read().decode()
fm = re.search(r'/forum/([a-z0-9\-]+)\.f(\d+)', home)
fhtml = op.open(base + '/forum/%s.f%s' % (fm.group(1), fm.group(2))).read().decode()
tm = re.search(r'/topic/([a-z0-9\-]+)\.t(\d+)', fhtml)
tslug, tid = tm.group(1), tm.group(2)
tpath = '/topic/%s.t%s' % (tslug, tid)

try:
    op2.open(base + '/topic/wrong-slug.t%s' % tid)
    check('canonical slug redirects', False, 'no redirect')
except urllib.error.HTTPError as e:
    check('canonical slug redirects', e.code == 301, '%s -> %s' % (e.code, e.headers.get('Location')))

html2 = op.open(base + tpath).read().decode()
m2 = re.search(r'name="csrf" value="([^"]+)"', html2)
check('reply form csrf present', bool(m2))
rresp = op.open(urllib.request.Request(base + tpath + '/reply', data=urllib.parse.urlencode(
    {'csrf': m2.group(1), 'body': 'Automated test reply [b]works[/b] :)'}).encode()))
check('reply posted', 'Automated test reply' in op.open(base + tpath).read().decode())
finish()
