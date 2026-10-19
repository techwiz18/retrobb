import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def post(path, fields):
    return op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode()))

# login as admin
html = op.open(base + '/login').read().decode()
post('/login', {'csrf': csrf(html), 'login': 'admin', 'password': 'admin123', 'next': '/'})

# Create a disposable topic, then delete it. Self-contained on purpose: this
# suite used to delete the topic bugtest.py happened to leave at id 4, which
# made it order-dependent (issue #9).
html = op.open(base + '/new-topic/3').read().decode()
url = post('/new-topic/3', {'csrf': csrf(html), 'title': 'Deltest disposable', 'body': 'delete me'}).url
tid = int(re.search(r'\.t(\d+)', url).group(1))

html = op.open(base + '/topic/x.t%d' % tid).read().decode()
post('/topic/%d/delete' % tid, {'csrf': csrf(html)})

try:
    op.open(base + '/topic/x.t%d' % tid)
    gone = False
except urllib.error.HTTPError as e:
    gone = e.code == 404
check('deleted topic is gone (404)', gone)
check('forum icons present', 'topic-icon' in op.open(base + '/forum/general-chat.f2').read().decode())
check('xsl served', 'xsl:stylesheet' in op.open(base + '/sitemap.xsl').read().decode())
finish()
