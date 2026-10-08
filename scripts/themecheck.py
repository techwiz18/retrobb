import urllib.request, http.cookiejar
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
op.open(base + '/theme/dark')
html = op.open(base + '/').read().decode()
print('dark class applied:', 'theme-dark' in html)
print('css has dark vars:', 'body.theme-dark' in op.open(base + '/assets/style-retro.css').read().decode())
