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

# --- shared refusal seam (issue #37) -------------------------------------
def send(op, path, fields, referer=None):
    """POST, follow the outcome; returns (final url, body, status)."""
    req = urllib.request.Request(base + path, data=urllib.parse.urlencode(fields).encode())
    if referer:
        req.add_header('Referer', base + referer)
    try:
        r = op.open(req)
        return r.geturl(), r.read().decode(), r.status
    except urllib.error.HTTPError as e:
        return e.geturl(), e.read().decode(), e.code

# A member acting on a report gets the board's 403 page, not a bare string.
reporter = session()
login(reporter, 'Guest_2003', 'password123')
tok = re.search(r'name="csrf" value="([^"]+)"', reporter.open(base + '/').read().decode()).group(1)
_, body, code = send(reporter, '/mod/report/999999/handle', {'csrf': tok, 'status': 'dismissed'})
check('member report action is the 403 page',
      code == 403 and '<nav' in body and 'class="footer"' in body, code)
check('member report action is not a bare string', body.strip() != 'Forbidden')

# A stale token returns to the queue the caller came from, same chrome.
url, body, code = send(admin, '/mod/report/999999/handle',
                       {'csrf': 'stale', 'status': 'dismissed'}, referer='/admin/reports')
check('report csrf failure returns to the admin queue',
      code == 200 and url.rstrip('/').endswith('/admin/reports'), url)
check('report csrf failure keeps admin chrome', 'Structure' in body)
check('report csrf failure says nothing was changed', 'security token expired' in body.lower())
check('report csrf failure is not a bare 419', 'CSRF mismatch' not in body, code)

url, body, code = send(mod, '/mod/report/999999/handle',
                       {'csrf': 'stale', 'status': 'dismissed'}, referer='/mod/reports')
check('report csrf failure returns to the mod queue',
      code == 200 and url.rstrip('/').endswith('/mod/reports'), url)
check('report csrf failure keeps mod chrome',
      'ModCP' in body and 'Structure' not in body)

# A form refusal (report already gone/nonexistent) lands on the caller's queue.
req = urllib.request.Request(base + '/mod/report/999999/resolve')
req.add_header('Referer', base + '/admin/reports')
try:
    r = mod.open(req)
    url, body, code = r.geturl(), r.read().decode(), r.status
except urllib.error.HTTPError as e:
    url, body, code = e.geturl(), e.read().decode(), e.code
check('resolve form refusal returns to the admin queue',
      code == 200 and url.rstrip('/').endswith('/admin/reports'), url)
check('resolve form refusal keeps chrome the mod can use',
      'ModCP' in body and 'Structure' not in body)
finish()
