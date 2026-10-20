"""Host-header trust (issue #13 / H1).

With board_url blank, nothing may build absolute links from the request Host:
canonicals are relative, the sitemap omits locs, and password-reset mail
refuses to send. With board_url set, absolute links come only from it.
"""
import os, subprocess, sys, urllib.request
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8081')
DB = os.environ.get('RETROBB_TEST_DB', 'retrobb_test')

def sql(q):
    r = subprocess.run(['sh', 'scripts/mysql-sql.sh', q], capture_output=True, text=True,
                       cwd=ROOT, env=dict(os.environ, RETROBB_TEST_DB=DB))
    return r.stdout.strip()

def set_board_url(v):
    sql("INSERT INTO settings (`key`,`value`) VALUES ('board_url','%s') "
        "ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)" % v.replace("'", "''"))

def php(snippet):
    r = subprocess.run(['docker', 'exec', 'retrobb-test', 'php', '-r', snippet],
                       capture_output=True, text=True)
    return (r.stdout or '').strip(), r.returncode

def reset_link():
    out, _ = php('require "/app/core/Db.php"; require "/app/core/helpers.php"; '
                 'require "/app/core/Mail.php"; echo RetroBB\\Core\\Mail::resetLink("tok");')
    return out

def fetch(path, host='evil.example'):
    req = urllib.request.Request(BASE + path, headers={'Host': host})
    try:
        return urllib.request.urlopen(req).read().decode()
    except Exception as e:
        return getattr(e, 'read', lambda: b'')().decode()

original_board_url = sql("SELECT `value` FROM settings WHERE `key`='board_url'")

try:
    # --- board_url blank: nothing may echo the forged Host ------------------
    set_board_url('')
    page = fetch('/forum/general-chat.f2?hostcheck=1')
    check('canonical does not echo the request Host', 'evil.example' not in page, page[:120])
    sitemap = fetch('/sitemap.xml')
    check('sitemap omits absolute locs without board_url', '<loc>' not in sitemap)
    check('reset link refuses without board_url', reset_link() == '', reset_link())

    # --- board_url set: absolute links come only from it --------------------
    set_board_url('https://board.example/forum')
    link = reset_link()
    check('reset link uses board_url when set',
          link.startswith('https://board.example/forum/reset-password?token='), link)
    sitemap = fetch('/sitemap.xml')
    check('sitemap uses board_url locs',
          '<loc>https://board.example/forum/' in sitemap and 'evil.example' not in sitemap)
finally:
    set_board_url(original_board_url)

finish()
