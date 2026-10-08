import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
base = 'http://localhost:8080'

def fresh():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get_cookies(path):
    op = fresh()
    try:
        r = op.open(base + path)
        r.read()
        return r.headers.get('Set-Cookie')
    except urllib.error.HTTPError as e:
        return e.headers.get('Set-Cookie')

print('--- guest GETs must NOT set cookies ---')
for path in ['/', '/members', '/forum/general-chat.f2', '/sitemap.xml',
             '/topic/what-was-your-first-forum.t2?page=99', '/nope', '/login?x=1']:
    sc = get_cookies(path)
    # /login renders a form (needs CSRF) so it legitimately sets one; topic pages track views
    print(path, '->', 'SET' if sc else 'none')
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
