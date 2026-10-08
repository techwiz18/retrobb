import re, urllib.request, urllib.parse, http.cookiejar
from concurrent.futures import ThreadPoolExecutor
import os
base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def make_session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def login(u, p):
    op = make_session()
    html = op.open(base + '/login').read().decode()
    tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    data = urllib.parse.urlencode({'csrf': tok, 'login': u, 'password': p, 'next': '/'}).encode()
    op.open(urllib.request.Request(base + '/login', data=data))
    return op

admin = login('admin', 'admin123')
html = admin.open(base + '/admin/settings').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
data = urllib.parse.urlencode({'csrf': tok, 'flood_seconds': '0', 'return': 'settings'}).encode()
admin.open(urllib.request.Request(base + '/admin/settings', data=data))
print('flood off for test')
html = admin.open(base + '/new-topic/2').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
data = urllib.parse.urlencode({'csrf': tok, 'title': 'Concurrency target', 'body': 'hammer time'}).encode()
url = admin.open(urllib.request.Request(base + '/new-topic/2', data=data)).url
tid = re.search(r'\.t(\d+)', url).group(1)
print('target topic:', tid)

def one_reply(i):
    try:
        op = login('admin', 'admin123')
        html = op.open(base + '/topic/x.t%s' % tid).read().decode()
        tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
        data = urllib.parse.urlencode({'csrf': tok, 'body': 'concurrent reply %d' % i}).encode()
        r = op.open(urllib.request.Request(base + '/topic/x.t%s/reply' % tid, data=data))
        return r.status
    except Exception as e:
        return 'ERR %s' % getattr(e, 'code', e)

with ThreadPoolExecutor(max_workers=20) as ex:
    codes = list(ex.map(one_reply, range(20)))
ok = sum(1 for c in codes if c == 200)
print('results:', codes)
print('concurrent replies ok:', ok, '/20')
