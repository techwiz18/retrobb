import os, re, sys, urllib.request, urllib.parse, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# theme switch is POST + CSRF (issue #2): pull the sessionless nav token.
home = op.open(base + '/').read().decode()
tok = re.search(r'name="anon_csrf" value="([^"]+)"', home).group(1)
op.open(urllib.request.Request(base + '/theme/dark', data=urllib.parse.urlencode({'anon_csrf': tok}).encode()))

html = op.open(base + '/').read().decode()
check('dark class applied', 'theme-dark' in html)
check('css has dark vars', 'body.theme-dark' in op.open(base + '/assets/style-retro.css').read().decode())
finish()
