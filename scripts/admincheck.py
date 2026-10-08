import re, urllib.request, urllib.parse, http.cookiejar
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
base = 'http://localhost:8080'
html = op.open(base + '/login').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
data = urllib.parse.urlencode({'csrf': tok, 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()
op.open(urllib.request.Request(base + '/login', data=data))
html = op.open(base + '/admin').read().decode()
print('admin 200:', 'AdminCP' in html)
print('struct-cat rows:', html.count('struct-cat'))
print('no stray topic-row in admin:', 'topic-row' not in html)
print('users listed:', 'dialup_dan' in html)
