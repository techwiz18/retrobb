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
turl = url if url.startswith('http') else base + url

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

# Double-report race (audit finding 1): N concurrent reports of the same post
# by the same user must leave exactly one open report (unique key + catch).
import time
stamp = 'race-report-%d' % int(time.time())
viewer = make_session()
lh = viewer.open(base + '/login').read().decode()
lt = re.search(r'name="csrf" value="([^"]+)"', lh).group(1)
viewer.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
    {'csrf': lt, 'login': 'Guest_2003', 'password': 'password123'}).encode()))
thtml = viewer.open(turl).read().decode()
pid = re.search(r'/post/(\d+)/report', thtml).group(1)

def one_report(i):
    try:
        op = make_session()
        lh = op.open(base + '/login').read().decode()
        lt = re.search(r'name="csrf" value="([^"]+)"', lh).group(1)
        op.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
            {'csrf': lt, 'login': 'Guest_2003', 'password': 'password123'}).encode()))
        fh = op.open(base + '/post/%s/report' % pid).read().decode()
        ft = re.search(r'name="csrf" value="([^"]+)"', fh).group(1)
        op.open(urllib.request.Request(base + '/post/%s/report' % pid, data=urllib.parse.urlencode(
            {'csrf': ft, 'reason': stamp}).encode()))
        return 200
    except Exception as e:
        return 'ERR %s' % getattr(e, 'code', e)

with ThreadPoolExecutor(max_workers=10) as ex2:
    list(ex2.map(one_report, range(10)))
q = admin.open(base + '/mod/reports').read().decode()
note('double-report reason occurrences: %d' % q.count(stamp))
check('10 concurrent reports leave one open report', q.count(stamp) == 1, q.count(stamp))
# Leave no trace (suites must clean up shared state): dismiss the probe.
# The handle link is taken from the stamped row itself, never page position.
pos = q.find(stamp)
ridm = re.search(r'/mod/report/(\d+)/handle', q[pos:] if pos >= 0 else q)
if ridm:
    dh = admin.open(base + '/mod/reports').read().decode()
    dtok = re.search(r'name="csrf" value="([^"]+)"', dh).group(1)
    admin.open(urllib.request.Request(base + '/mod/report/%s/handle' % ridm.group(1), data=urllib.parse.urlencode(
        {'csrf': dtok, 'status': 'dismissed', 'note': ''}).encode()))
q2 = admin.open(base + '/mod/reports').read().decode()
check('probe report dismissed after race test', stamp not in q2)
finish()
