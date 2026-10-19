import os, re, sys, urllib.request, urllib.parse, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

html = op.open(base + '/login').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
op.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode(
    {'csrf': tok, 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()))

check('dashboard renders', 'Dashboard' in op.open(base + '/admin').read().decode())
struct = op.open(base + '/admin/structure').read().decode()
check('structure lists categories', struct.count('struct-cat') > 0)
check('structure has no stray topic rows', 'topic-row' not in struct)
check('users page lists members', 'dialup_dan' in op.open(base + '/admin/users').read().decode())
check('modlog page renders', 'Mod log' in op.open(base + '/admin/modlog').read().decode())
check('bans page renders', 'Bans' in op.open(base + '/admin/bans').read().decode())
finish()
