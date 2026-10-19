import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def fresh():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get_cookies(path):
    op = fresh()
    try:
        r = op.open(base + path)
        r.read()
        return '; '.join(r.headers.get_all('Set-Cookie') or [])
    except urllib.error.HTTPError as e:
        return '; '.join(e.headers.get_all('Set-Cookie') or [])

# A session (PHPSESSID) is the thing that must stay lazy. Layout pages also set
# the stateless retrobb_csrf cookie for the POST switchers (#2); /login sets a
# session (it renders a form); /sitemap.xml (layout-less) stays clean.
for path, expect_session in [('/', False), ('/members', False), ('/forum/general-chat.f2', False),
                             ('/sitemap.xml', False), ('/nope', False), ('/login?x=1', True)]:
    cookies = get_cookies(path)
    started = 'PHPSESSID' in cookies
    check('guest GET %s session=%s' % (path, expect_session), started == expect_session,
          '[' + cookies[:70] + ']')

# login / settings roundtrip
op = fresh()
html = op.open(base + '/login').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
r = op.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
    {'csrf': tok, 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()))
admin = op.open(base + '/admin').read().decode()
check('admin ok after login', 'Dashboard' in admin)
html = op.open(base + '/admin/settings').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
r = op.open(urllib.request.Request(base + '/admin/settings', data=urllib.parse.urlencode(
    {'csrf': tok, 'board_name': 'RetroBB', 'return': 'settings'}).encode()))
check('settings flash survives redirect', 'Settings saved' in op.open(r.url).read().decode())
finish()
