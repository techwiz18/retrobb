import re, urllib.request, urllib.parse, http.cookiejar
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
# theme switch is POST + CSRF (issue #2): pull the sessionless nav token.
home = op.open(base + '/').read().decode()
tok = re.search(r'name="anon_csrf" value="([^"]+)"', home).group(1)
data = urllib.parse.urlencode({'anon_csrf': tok}).encode()
op.open(urllib.request.Request(base + '/theme/dark', data=data))
html = op.open(base + '/').read().decode()
print('dark class applied:', 'theme-dark' in html)
print('css has dark vars:', 'body.theme-dark' in op.open(base + '/assets/style-retro.css').read().decode())
