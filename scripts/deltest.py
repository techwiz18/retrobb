import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
base = 'http://localhost:8080'
html = op.open(base + '/login').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
data = urllib.parse.urlencode({'csrf': tok, 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()
op.open(urllib.request.Request(base + '/login', data=data))
# delete bugtest topic (id 4)
html = op.open(base + '/topic/bugtest-topic-please-ignore.t4').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
data = urllib.parse.urlencode({'csrf': tok}).encode()
try:
    r = op.open(urllib.request.Request(base + '/topic/4/delete', data=data))
    print('delete final url:', r.url)
except urllib.error.HTTPError as e:
    print('delete HTTPError:', e.code)
try:
    op.open(base + '/topic/bugtest-topic-please-ignore.t4')
    print('topic still exists: BAD')
except urllib.error.HTTPError as e:
    print('topic gone after delete:', e.code == 404)
print('forum icons present:', 'topic-icon' in op.open(base + '/forum/general-chat.f2').read().decode())
print('xsl served:', 'xsl:stylesheet' in op.open(base + '/assets/sitemap.xsl').read().decode())
