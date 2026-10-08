import re, urllib.request, urllib.parse, http.cookiejar
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def login(op, u, p):
    html = op.open(base + '/login').read().decode()
    tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    data = urllib.parse.urlencode({'csrf': tok, 'login': u, 'password': p}).encode()
    op.open(urllib.request.Request(base + '/login', data=data))

admin = session()
login(admin, 'admin', 'admin123')
html = admin.open(base + '/mod/reports').read().decode()
print('admin sees adminnav on queue:', 'adminnav' in html and 'Mod log' in html)

mod = session()
login(mod, 'PixelMod', 'password123')
html = mod.open(base + '/mod/reports').read().decode()
print('mod sees plain queue:', 'adminnav' not in html and 'Mod queue' in html)
