import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

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

print('--- guest GETs must NOT start a session (stateless CSRF cookie is fine) ---')
for path in ['/', '/members', '/forum/general-chat.f2', '/sitemap.xml',
             '/topic/what-was-your-first-forum.t2?page=99', '/nope', '/login?x=1']:
    sc = get_cookies(path)
    # A session (PHPSESSID) is the thing that must stay lazy. Layout pages now
    # also set the stateless retrobb_csrf cookie for the POST switchers (#2);
    # /login legitimately does both, /sitemap.xml (layout-less) stays clean.
    kind = 'SESSION' if 'PHPSESSID' in sc else ('none' if not sc else 'csrf-only')
    print(path, '->', kind, ('[' + sc[:80] + ']' if sc else ''))
print('--- login/logout roundtrip ---')
op = fresh()
html = op.open(base + '/login').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
data = urllib.parse.urlencode({'csrf': tok, 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()
r = op.open(urllib.request.Request(base + '/login', data=data))
print('login lands:', r.url)
html = op.open(base + '/admin').read().decode()
print('admin ok:', 'Dashboard' in html)
html = op.open(base + '/admin/settings').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
data = urllib.parse.urlencode({'csrf': tok, 'board_name': 'RetroBB', 'return': 'settings'}).encode()
r = op.open(urllib.request.Request(base + '/admin/settings', data=data))
html = op.open(r.url).read().decode()
print('settings saved flash survives redirect:', 'Settings saved' in html)
print('LAZY TESTS DONE')
