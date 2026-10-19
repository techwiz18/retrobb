import os, re, sys, urllib.request, urllib.parse, http.cookiejar
from concurrent.futures import ThreadPoolExecutor
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def make_session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def login(u, p):
    op = make_session()
    html = op.open(base + '/login').read().decode()
    tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    op.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
        {'csrf': tok, 'login': u, 'password': p, 'next': '/'}).encode()))
    return op

admin = login('admin', 'admin123')
html = admin.open(base + '/admin/settings').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
admin.open(urllib.request.Request(base + '/admin/settings', data=urllib.parse.urlencode(
    {'csrf': tok, 'flood_seconds': '0', 'return': 'settings'}).encode()))

html = admin.open(base + '/new-topic/2').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
url = admin.open(urllib.request.Request(base + '/new-topic/2', data=urllib.parse.urlencode(
    {'csrf': tok, 'title': 'Concurrency target', 'body': 'hammer time'}).encode())).url
tid = re.search(r'\.t(\d+)', url).group(1)

def one_reply(i):
    try:
        op = login('admin', 'admin123')
        html = op.open(base + '/topic/x.t%s' % tid).read().decode()
        tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
        r = op.open(urllib.request.Request(base + '/topic/x.t%s/reply' % tid, data=urllib.parse.urlencode(
            {'csrf': tok, 'body': 'concurrent reply %d' % i}).encode()))
        return r.status
    except Exception as e:
        return 'ERR %s' % getattr(e, 'code', e)

with ThreadPoolExecutor(max_workers=20) as ex:
    codes = list(ex.map(one_reply, range(20)))
ok = sum(1 for c in codes if c == 200)
note('concurrent reply codes: %s' % codes)
check('20 concurrent replies all succeed', ok == 20, '%d/20' % ok)
finish()
