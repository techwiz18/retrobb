import os, re, subprocess, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.environ.get('RETROBB_TEST_DB', 'retrobb_test')
base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

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

# 3. failed logins are counted in the DB per remote address + account, so a
#    fresh cookie jar does not reset the count (issue #33). DB truth, not page
#    text: the user is logged in on the live board while this runs.
def sql(q):
    r = subprocess.run(['sh', 'scripts/mysql-sql.sh', q], cwd=ROOT, capture_output=True, text=True,
                       env=dict(os.environ, RETROBB_TEST_DB=DB))
    return r.stdout.strip()

def key_row(login):
    return "SUBSTRING_INDEX(ip,'|',-1)='%s'" % login

def attempts(login='guest_2003'):
    out = sql('SELECT attempts FROM login_attempts WHERE ' + key_row(login))
    return int(out) if out else 0

def clear_key(login='guest_2003'):
    sql('DELETE FROM login_attempts WHERE ' + key_row(login))

def fail_login(op, login, pw='wrong-password'):
    _, _, html = get(op, '/login')
    return post(op, '/login', {'csrf': csrf(html), 'login': login, 'password': pw})[1]

def logged_in(op):
    # The board index also shows Guest_2003 as a post author, so key off nav.
    return 'Log out' in get(op, '/')[2]

clear_key()
clear_key('prune_probe')
# a stale row the one-day prune should remove
sql("INSERT INTO login_attempts (ip, attempts, last_at) VALUES ('192.0.2.9|prune_probe',3,NOW() - INTERVAL 2 DAY)"
    " ON DUPLICATE KEY UPDATE last_at=NOW() - INTERVAL 2 DAY")

page = fail_login(session(), 'Guest_2003')  # mixed case: key must be lowercased
check('failure writes an attempt row keyed ip|login', attempts() == 1, 'attempts=%d' % attempts())
check('login error is the ordinary one', 'Invalid login.' in page)

for _ in range(9):
    fail_login(session(), 'guest_2003')  # new cookie jar each time
check('10 failures in 15 min all counted despite cleared cookies', attempts() == 10, 'attempts=%d' % attempts())
check('one-day prune removed the stale row', sql("SELECT COUNT(*) FROM login_attempts WHERE " + key_row('prune_probe')) == '0')

throttled = session()
page = fail_login(throttled, 'guest_2003', 'password123')  # right password, blocked key
check('blocked attempt rejects the right password', not logged_in(throttled))
check('blocked attempt shows the ordinary login error', 'Invalid login.' in page)

# a successful login clears the key
clear_key()
fail_login(session(), 'guest_2003')
fail_login(session(), 'guest_2003')
check('count grows before the successful login', attempts() == 2, 'attempts=%d' % attempts())
ok = session()
fail_login(ok, 'guest_2003', 'password123')
check('successful login works once under the threshold', logged_in(ok))
check('successful login clears the attempt row', attempts() == 0, 'attempts=%d' % attempts())
clear_key()
finish()
