import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    url = path if path.startswith('http') else base + path
    try:
        r = op.open(url)
        return r.status, r.url, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, url, e.read().decode()

def post(op, path, fields):
    r = op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(fields, doseq=True).encode()))
    return r.url, r.read().decode()

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    _, _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

admin = session()
login(admin, 'admin', 'admin123')
mod = session()
login(mod, 'PixelMod', 'password123')

# 1. demoting a mod revokes powers mid-session, restoring brings them back
code, _, _ = get(mod, '/mod/reports')
check('mod reaches queue before demote', code == 200, code)
_, _, html = get(admin, '/admin/users')
post(admin, '/admin/user/2/group', {'csrf': csrf(html), 'group': 'member'})
code, _, _ = get(mod, '/mod/reports')
check('demoted mod loses queue', code == 403, code)
_, _, html = get(admin, '/admin/users')
post(admin, '/admin/user/2/group', {'csrf': csrf(html), 'group': 'mod'})
code, _, _ = get(mod, '/mod/reports')
check('restored mod regains queue', code == 200, code)

# 2. ban while online boots the session
victim = session()
login(victim, 'dialup_dan', 'password123')
_, _, html = get(victim, '/')
check('victim logged in', 'dialup_dan' in html)
_, _, html = get(admin, '/members/dialup-dan.u3')
post(admin, '/members/3/ban', {'csrf': csrf(html), 'reason': 'session test', 'days': '1'})
_, _, html = get(victim, '/')
check('banned user booted mid-session', 'dialup_dan' not in html and 'Log in' in html)
check('ban reason shown', 'banned' in html.lower())
# unban, confirm login works again
_, _, html = get(admin, '/admin/bans')
m = re.search(r'/admin/unban/(\d+)', html)
post(admin, '/admin/unban/' + m.group(1), {'csrf': csrf(html)})
login(victim, 'dialup_dan', 'password123')
_, _, html = get(victim, '/')
check('login works after unban', 'dialup_dan' in html)

# 3. Refusal seam (issue #38): a stale token re-renders a page with chrome and
# a message, and the action never runs.
def stale(op, path, fields):
    """POST a deliberately stale token; never follow a redirect."""
    data = urllib.parse.urlencode(dict(fields, csrf='stale')).encode()
    try:
        r = op.open(urllib.request.Request(base + path, data=data))
        return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()

def refused(html):
    text = html.lower()
    return ('session expired' in text and '<nav' in html and 'class="footer"' in html
            and 'csrf mismatch' not in text)

home = get(admin, '/')[2]
fm = re.search(r'/forum/([a-z0-9\-]+)\.f(\d+)', home)
fhtml = get(admin, '/forum/%s.f%s' % (fm.group(1), fm.group(2)))[2]
tm = re.search(r'/topic/([a-z0-9\-]+)\.t(\d+)', fhtml)
thtml = get(admin, '/topic/%s.t%s' % (tm.group(1), tm.group(2)))[2]
pid = re.search(r'#p(\d+)', thtml).group(1)

code, body = stale(mod, '/pm/new', {'to': 'admin', 'subject': 'CSRF_PM_38', 'body': 'CSRF_PM_BODY_38'})
check('pm csrf refusal shows chrome + message', refused(body), code)
check('pm csrf refusal keeps the typed body', 'CSRF_PM_BODY_38' in body)
check('pm csrf refusal sends nothing', 'CSRF_PM_38' not in get(mod, '/pm/sent')[2])

code, body = stale(mod, '/post/%s/react' % pid, {'reaction': 'like'})
check('reaction csrf refusal shows chrome + message', refused(body), code)

code, body = stale(mod, '/settings/profile', {'form': 'profile', 'email': 'x@example.com', 'bio': 'CSRF_BIO_38'})
check('profile csrf refusal shows chrome + message', refused(body), code)
check('profile csrf refusal saves nothing', 'CSRF_BIO_38' not in get(mod, '/settings/profile')[2])

code, body = stale(session(), '/login', {'login': 'admin', 'password': 'admin123', 'next': '/pm/sent'})
check('login csrf refusal shows chrome + message', refused(body), code)
check('login csrf refusal keeps the destination', 'name="next"' in body and '/pm/sent' in body)
check('login csrf refusal never echoes the password', 'admin123' not in body)

code, body = stale(mod, '/members/3/warn', {'reason': 'CSRF_WARN_38'})
check('moderation csrf refusal shows chrome + message', refused(body), code)
check('moderation csrf refusal records nothing', 'CSRF_WARN_38' not in get(mod, '/members/dialup-dan.u3')[2])

# 4. A guest sent to the login form comes back to where they were going.
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

guest = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def redirect_to(path):
    try:
        guest.open(base + path)
        return 0, ''
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location') or ''

code, loc = redirect_to('/pm/1')
check('guest PM link returns to the message after login',
      code == 302 and 'next=%2Fpm%2F1' in loc, '%s %s' % (code, loc))
code, loc = redirect_to('/settings/profile')
check('guest settings link returns after login',
      code == 302 and 'next=%2Fsettings%2Fprofile' in loc, '%s %s' % (code, loc))

# 5. No bare CSRF refusal survives outside the shared module (issue #38).
for name in ['AuthController', 'PmController', 'ProfileController', 'ReactionController', 'ModController']:
    src = open(os.path.join(ROOT, 'controllers', name + '.php')).read()
    check('refusal goes through the shared module: ' + name,
          'CSRF mismatch' not in src and '419' not in src and 'Csrf::verify' not in src)
finish()
