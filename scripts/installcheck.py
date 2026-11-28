"""Installer re-run guard (issue #13 / H2).

Two scenarios:
1. An installed board with config.php + users and NO storage/installed.lock
   must refuse the wizard, send the security headers, and ignore a direct
   owner-step POST. (The repo's real lock is moved aside for this and restored.)
2. An isolated fresh board on :8098: an empty schema resumes the wizard, a
   direct owner POST is rejected out of order, and a schema with content but
   zero users still counts as installed.
"""
import http.cookiejar
import json, os, re, subprocess, sys, time, urllib.parse, urllib.request, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOCK = os.path.join(ROOT, 'storage', 'installed.lock')
CFG = os.path.join(ROOT, 'storage', 'test-install-config.php')
DB = os.environ.get('RETROBB_INSTALL_DB', 'retrobb_install_check')
NAME = os.environ.get('RETROBB_INSTALL_NAME', 'retrobb-install-check')
PORT = int(os.environ.get('RETROBB_INSTALL_PORT', '8098'))
BASE = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8081')
FRESH = 'http://localhost:%d' % PORT

def sh(*args):
    return subprocess.run(args, capture_output=True, text=True)

def server_sql(q):
    sh('docker', 'exec', 'retrobb-mysql', 'mysql', '-uroot', '-proot', '-e', q)

def new_opener():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def req(op, base, path, fields=None, method=None):
    data = urllib.parse.urlencode(fields).encode() if fields is not None else None
    r = urllib.request.Request(base + path, data=data, method=method)
    try:
        resp = op.open(r)
        return resp.status, resp.read().decode(), resp.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode(), e.headers

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

def status(op, base, path):
    try:
        r = op.open(base + path)
        return r.code, r.headers.get('Location')
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location')

moved_lock = None
try:
    # Recover a lock left behind by a killed previous run before moving it.
    bak = LOCK + '.installcheck-bak'
    if not os.path.isfile(LOCK) and os.path.isfile(bak):
        os.replace(bak, LOCK)

    # --- 1. installed board, no lock --------------------------------------
    if os.path.isfile(LOCK):
        moved_lock = LOCK + '.installcheck-bak'
        os.replace(LOCK, moved_lock)
    check('test board has no install lock', not os.path.isfile(LOCK))

    op = new_opener()
    code, body, hdrs = req(op, BASE, '/install.php')
    check('installed board refuses the wizard (config + users)',
          'Already installed' in body and 'Before we begin' not in body, body[:100])
    check('installer sends X-Frame-Options', hdrs.get('X-Frame-Options') == 'SAMEORIGIN')
    check('installer sends nosniff', hdrs.get('X-Content-Type-Options') == 'nosniff')
    check('installer hides X-Powered-By', hdrs.get('X-Powered-By') is None)
    code, body, _ = req(op, BASE, '/install.php', {
        'form_step': 'owner', 'admin_user': 'evil', 'admin_email': 'evil@example.test',
        'password': 'password123', 'password2': 'password123',
    }, method='POST')
    check('direct owner POST is refused on an installed board',
          'Already installed' in body and 'Welcome aboard' not in body, body[:100])
    op_nr = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    code, loc = status(op_nr, BASE, '/install.php?force=1')
    check('force requires a super session (redirects home)',
          code == 302 and (loc or '') == '/', '%s -> %s' % (code, loc))

    # --- 2. isolated fresh board ------------------------------------------
    server_sql("DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET utf8mb4 "
               "COLLATE utf8mb4_unicode_ci; GRANT ALL ON `%s`.* TO 'retrobb'@'%%'; FLUSH PRIVILEGES;"
               % (DB, DB, DB))
    with open(CFG, 'w', encoding='utf-8') as fh:
        fh.write("<?php\ndeclare(strict_types=1);\nreturn [\n"
                 "    'mysql_host' => 'retrobb-mysql',\n    'mysql_port' => 3306,\n"
                 "    'mysql_db' => '%s',\n    'mysql_user' => 'retrobb',\n"
                 "    'mysql_pass' => 'retrobb',\n];\n" % DB)
    sh('docker', 'rm', '-f', NAME)
    sh('docker', 'run', '-d', '--rm', '--name', NAME, '-p', '%d:8000' % PORT,
       '-v', ROOT + ':/app', '-v', CFG + ':/app/config.php', '-w', '/app',
       '--network', 'retrobb-net', 'retrobb-php',
       'php', '-S', '0.0.0.0:8000', '-t', 'public', 'public/router.php')
    for _ in range(30):
        try:
            urllib.request.urlopen(FRESH + '/install.php', timeout=2).read()
            break
        except Exception:
            time.sleep(0.3)

    op2 = new_opener()
    code, body, _ = req(op2, FRESH, '/install.php')
    check('empty schema resumes the wizard', 'Before we begin' in body, body[:100])
    code, body, _ = req(op2, FRESH, '/install.php?step=done')
    check('bare ?step=done does not show success',
          'Welcome aboard' not in body and 'Before we begin' in body, body[:100])
    code, body, _ = req(op2, FRESH, '/install.php?step=database')
    m = re.search(r'name="csrf" value="([^"]+)"', body)
    check('database step renders a csrf token', bool(m))
    if m:
        code, body, _ = req(op2, FRESH, '/install.php', {
            'form_step': 'owner', 'csrf': m.group(1), 'admin_user': 'evil',
            'admin_email': 'evil@example.test', 'password': 'password123', 'password2': 'password123',
        }, method='POST')
        check('owner step rejected out of order',
              'Complete the earlier installation steps first' in body, body[:160])

    # migrate but leave users empty, then add content: still installed
    sh('docker', 'run', '--rm', '-v', ROOT + ':/app', '-v', CFG + ':/app/config.php',
       '-w', '/app', '--network', 'retrobb-net', 'retrobb-php', 'php', 'bin/migrate.php')

    # --- settings registry parity (issue #44) ------------------------------
    # Golden snapshot of the rows a fresh board has always produced; the
    # registry is only correct if it seeds exactly this.
    EXPECTED = {
        'avatar_max_height': '200', 'avatar_max_kb': '100', 'avatar_max_width': '200',
        'board_name': 'RetroBB', 'board_tagline': 'An old-school forum for the modern web',
        'board_url': '', 'captcha_provider': 'honeypot', 'captcha_secret': '',
        'captcha_sitekey': '', 'default_skin': 'classic', 'default_theme': 'auto',
        'delete_window_mins': '30', 'edit_window_mins': '30',
        'feature_alerts': '1', 'feature_avatars_link': '1', 'feature_avatars_upload': '1',
        'feature_mentions': '1', 'feature_pms': '1', 'feature_reactions': '1',
        'flood_seconds': '30', 'mail_from': '', 'posts_per_page': '15',
        'skin_selector': '1', 'theme_auto': '1', 'theme_dark': '1', 'theme_light': '1',
        'topics_per_page': '25',
    }
    raw = sh('docker', 'exec', 'retrobb-mysql', 'mysql', '-uroot', '-proot', '-N', '-B', DB,
             '-e', "SELECT `key`, `value` FROM settings").stdout
    rows = dict(line.split('\t', 1) for line in raw.splitlines() if '\t' in line)
    check('fresh migrate seeds exactly the golden settings', rows == EXPECTED,
          str(sorted(set(rows.items()) ^ set(EXPECTED.items()))))

    def php(code):
        r = sh('docker', 'run', '--rm', '-v', ROOT + ':/app', '-v', CFG + ':/app/config.php',
               '-w', '/app', '--network', 'retrobb-net', 'retrobb-php', 'php', '-r', code)
        return r.stdout.strip()

    reg = php('require "core/Settings.php"; echo json_encode(\RetroBB\Core\Settings::defaults());')
    check('registry defaults are the fresh-board rows', reg and json.loads(reg) == EXPECTED, reg[:200])
    check('registry coerce clamps a per-page limit',
          php('require "core/Settings.php"; echo \RetroBB\Core\Settings::coerce("posts_per_page", "999");') == '50')
    check('registry coerce falls back for a non-numeric limit',
          php('require "core/Settings.php"; echo \RetroBB\Core\Settings::coerce("posts_per_page", "abc");') == '15')
    check('registry coerce rejects an unknown captcha provider',
          php('require "core/Settings.php"; var_export(\RetroBB\Core\Settings::coerce("captcha_provider", "bogus"));') == 'NULL')
    check('registry coerce truncates a long tagline',
          php('require "core/Settings.php"; echo strlen(\RetroBB\Core\Settings::coerce("board_tagline", str_repeat("x", 250)));') == '200')
    check('registry maps feature aliases to keys',
          php('require "core/Settings.php"; echo implode(",", [\RetroBB\Core\Settings::featureKey("alerts"), \RetroBB\Core\Settings::featureKey("skin_selector"), \RetroBB\Core\Settings::featureKey("brand_new")]);')
          == 'feature_alerts,skin_selector,feature_brand_new')

    sh('docker', 'exec', 'retrobb-mysql', 'mysql', '-uroot', '-proot', '-N', '-B', DB,
       '-e', "INSERT INTO categories (title, sort) VALUES ('Existing',0)")
    code, body, _ = req(new_opener(), FRESH, '/install.php')
    check('content with zero users still counts as installed',
          'Already installed' in body and 'Before we begin' not in body, body[:100])
finally:
    sh('docker', 'rm', '-f', NAME)
    server_sql("DROP DATABASE IF EXISTS `%s`;" % DB)
    if os.path.isfile(CFG):
        os.remove(CFG)
    if moved_lock and os.path.isfile(moved_lock):
        os.replace(moved_lock, LOCK)

finish()
