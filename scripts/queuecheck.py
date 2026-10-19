import os, re, sys, urllib.request, urllib.parse, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def login(op, u, p):
    html = op.open(base + '/login').read().decode()
    tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
    op.open(urllib.request.Request(base + '/login',
        data=urllib.parse.urlencode({'csrf': tok, 'login': u, 'password': p}).encode()))

admin = session()
login(admin, 'admin', 'admin123')
html = admin.open(base + '/mod/reports').read().decode()
check('admin sees mod nav + bans link', 'Mod queue' in html and '/mod/bans' in html)
check('admin keeps AdminCP pill', 'AdminCP' in html)

mod = session()
login(mod, 'PixelMod', 'password123')
html = mod.open(base + '/mod/reports').read().decode()
check('mod sees mod nav', 'Mod queue' in html and '/mod/bans' in html)
check('mod has no AdminCP pill', 'AdminCP' not in html)
html = mod.open(base + '/mod').read().decode()
check('mod sees ModCP home', 'ModCP — Dashboard' in html)
finish()
