"""Super-admin tier checks. Canonical fixture ids: admin=1 (super),
PixelMod=2 (mod), dialup_dan=3 (member, the promoted one)."""
import os, re, sys, urllib.request, urllib.parse, http.cookiejar, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    html = op.open(base + '/login').read().decode()
    op.open(urllib.request.Request(base + '/login',
        data=urllib.parse.urlencode({'csrf': csrf(html), 'login': u, 'password': p}).encode()))

def get(op, path):
    try:
        return op.open(base + path).read().decode(), 200
    except urllib.error.HTTPError as e:
        return '', e.code

def post(op, formpage, path, fields):
    html = op.open(base + formpage).read().decode()
    fields['csrf'] = csrf(html)
    try:
        return op.open(urllib.request.Request(base + path,
            data=urllib.parse.urlencode(fields).encode())).read().decode(), 200
    except urllib.error.HTTPError as e:
        return '', e.code

super_s, admin2 = session(), session()
login(super_s, 'admin', 'admin123')
# promote a member to a NON-super admin so the tier gates are testable
post(super_s, '/admin/users', '/admin/user/3/group', {'group': 'admin'})
login(admin2, 'dialup_dan', 'password123')

_, c1 = get(admin2, '/admin/settings')
check('non-super admin blocked from settings', c1 == 403, c1)
_, c2 = get(admin2, '/admin/import')
check('non-super admin blocked from import', c2 == 403, c2)
_, c3 = post(admin2, '/admin/users', '/admin/user/2/group', {'group': 'member'})
check('non-super admin cannot set groups', c3 == 403, c3)
u, _ = get(admin2, '/admin/users')
check('users page hides super controls', 'Make super' not in u and 'name="group"' not in u)
d, _ = get(admin2, '/admin')
check('dashboard hides super cards', 'Run database updates' not in d and 'Import' not in d)
q, _ = get(admin2, '/mod/reports')
check('non-super admin keeps mod queue', 'Mod queue' in q)

# staff shields: only a super may warn/ban a mod
w, _ = post(admin2, '/members/pixelmod.u2', '/members/2/warn', {'reason': 'admin2 warn mod'})
check('non-super admin cannot warn a mod', 'super admins can warn staff' in w.lower())
_, wc = post(super_s, '/members/pixelmod.u2', '/members/2/warn', {'reason': 'super warn mod ok'})
check('super can warn a mod', wc == 200, wc)
bw, _ = post(admin2, '/members/pixelmod.u2', '/members/2/ban', {'reason': 'admin2 ban mod', 'days': '1'})
check('non-super admin cannot ban a mod', 'super admins can ban staff' in bw.lower())

# grant super + revoke + self-protection
_, gc = post(super_s, '/admin/users', '/admin/user/3/super', {})
check('super grants super', gc == 200, gc)
s, sc = get(admin2, '/admin/settings')
check('newly-super admin reaches settings', sc == 200 and 'Board name' in s, sc)
_, rc = post(super_s, '/admin/users', '/admin/user/3/super', {})
check('super revokes super', rc == 200, rc)
_, sc2 = get(admin2, '/admin/settings')
check('revoked admin blocked again', sc2 == 403, sc2)
post(super_s, '/admin/users', '/admin/user/1/super', {})
check('self super-revoke blocked', 'Board name' in super_s.open(base + '/admin/settings').read().decode())

# clean up the promoted admin
post(super_s, '/admin/users', '/admin/user/3/group', {'group': 'member'})

# --- Plugin lifecycle has one owner (issue #46) --------------------------
# A fixture plugin with no row must read as enabled when it loads, when it is
# listed, and when it is toggled; the toggle is one atomic write (parallel
# flips land on the parity value); uninstall loads a disabled plugin's
# bootstrap through the same accessor so its hook still fires.
import glob, shutil, subprocess

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.environ.get('RETROBB_TEST_DB', 'retrobb_test')
TESTCFG = os.path.join(ROOT, 'storage', 'test-config.php')
plugname = 'zz46-lifecycle-%d' % os.getpid()
marker = 'zz46-marker-%d' % os.getpid()
pdir = os.path.join(ROOT, 'plugins', plugname)
unmark = os.path.join(ROOT, 'storage', plugname + '.uninstalled')


def sql(q):
    r = subprocess.run(['sh', 'scripts/mysql-sql.sh', q], capture_output=True, text=True,
                       cwd=ROOT, env=dict(os.environ, RETROBB_TEST_DB=DB))
    return r.stdout.strip()


def toggle(name):
    return post(super_s, '/admin/plugins', '/admin/plugin/' + name, {})


def row_state(name):
    return sql("SELECT COUNT(*), COALESCE(MAX(enabled), -1) FROM plugins WHERE name='%s'" % name)


def listing_says(page, word):
    pos = page.find('<b>%s</b>' % plugname)
    return pos >= 0 and word in page[pos:pos + 600]


for stale in glob.glob(os.path.join(ROOT, 'plugins', 'zz46-lifecycle-*')):
    shutil.rmtree(stale, ignore_errors=True)
try:
    os.makedirs(pdir)
    with open(os.path.join(pdir, 'plugin.json'), 'w') as f:
        f.write('{"name":"%s","version":"0","description":"lifecycle probe","author":"test"}' % plugname)
    with open(os.path.join(pdir, 'bootstrap.php'), 'w') as f:
        f.write("""<?php
declare(strict_types=1);
use RetroBB\\Core\\Hooks;
Hooks::add_action('footer', function () { echo '<span id="%s">%s</span>'; });
Hooks::add_action('plugin_uninstalled', function ($name) {
    file_put_contents(dirname(__DIR__, 2) . '/storage/%s.uninstalled', (string) $name);
});
""" % (marker, marker, plugname))

    home, _ = get(super_s, '/')
    page, _ = get(super_s, '/admin/plugins')
    check('no-row plugin loads by default', marker in home)
    check('no-row plugin lists as on', listing_says(page, '[on]'))
    check('no-row plugin has no stored row', row_state(plugname) == '0\t-1', row_state(plugname))

    body, _ = toggle(plugname)
    check('first toggle of a no-row plugin stores off', row_state(plugname) == '1\t0', row_state(plugname))
    check('toggle did not error', 'Could not save plugin state.' not in body)
    page, _ = get(super_s, '/admin/plugins')
    home, _ = get(super_s, '/')
    check('listing agrees with the stored off state', listing_says(page, '[off]'))
    check('disabled plugin no longer loads', marker not in home)

    body, _ = toggle(plugname)
    check('second toggle stores on', row_state(plugname) == '1\t1', row_state(plugname))
    check('toggle back did not error', 'Could not save plugin state.' not in body)

    # Disabled plugins do not load, so uninstall must fetch the bootstrap itself.
    toggle(plugname)
    check('fixture disabled before uninstall', row_state(plugname) == '1\t0', row_state(plugname))
    post(super_s, '/admin/plugins', '/admin/plugin/%s/uninstall' % plugname, {})
    check('uninstall fires the disabled plugin hook', os.path.exists(unmark))
    check('uninstall forgets the row and files', row_state(plugname) == '0\t-1' and not os.path.isdir(pdir),
          row_state(plugname))

    # Eight parallel flips from "no row" (= enabled) must land on enabled and
    # leave a single intact row, never a torn/duplicated one.
    php = ('require "/app/core/Db.php"; require "/app/core/Plugins.php";'
           '\\RetroBB\\Core\\Plugins::toggle("%s");' % plugname)
    procs = [subprocess.Popen(
        ['docker', 'run', '--rm', '-v', ROOT + ':/app', '-v', TESTCFG + ':/app/config.php',
         '-w', '/app', '--network', 'retrobb-net', 'retrobb-php', 'php', '-r', php],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True) for _ in range(8)]
    codes = []
    for p in procs:
        out, err = p.communicate()
        codes.append(p.returncode if p.returncode == 0 else (err or out).strip().splitlines()[-1:])
    check('eight concurrent toggles all succeed', all(c == 0 for c in codes), codes)
    check('concurrent toggles leave one intact row at even parity',
          row_state(plugname) == '1\t1', row_state(plugname))
finally:
    sql("DELETE FROM plugins WHERE name='%s'" % plugname)
    shutil.rmtree(pdir, ignore_errors=True)
    if os.path.exists(unmark):
        os.remove(unmark)

finish()
