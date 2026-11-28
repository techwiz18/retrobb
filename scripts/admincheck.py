import os, re, subprocess, sys, urllib.request, urllib.parse, http.cookiejar
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
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

# --- shared refusal seam (issue #36) -------------------------------------
# A stale token must render the board's own page with a message, not bounce
# to the dashboard and lose what was typed.
def post_raw(path, fields):
    return op.open(urllib.request.Request(base + path,
        data=urllib.parse.urlencode(fields).encode())).read().decode()

refused = post_raw('/admin/settings',
                   {'csrf': 'stale', 'board_name': 'CSRF_KEPT_36', 'return': 'settings'})
check('csrf refusal shows a message', 'session expired' in refused.lower())
check('csrf refusal keeps board chrome',
      '<nav' in refused and 'class="footer"' in refused)
check('csrf refusal preserves submitted input',
      'name="board_name"' in refused and 'CSRF_KEPT_36' in refused)
after = op.open(base + '/admin/settings').read().decode()
check('csrf refusal saves nothing', 'CSRF_KEPT_36' not in after)
cat = post_raw('/admin/add-category', {'csrf': 'stale', 'title': 'CSRF_CAT_36'})
check('csrf refusal is shared by other admin forms',
      'CSRF_CAT_36' in cat and 'session expired' in cat.lower())

# The return path is local-only: no scheme, scheme-relative, backslash or
# header-injection value may become a destination.
php = r'''
require "/app/core/Guard.php";
$bad = 0;
$cases = [
  ["/admin/settings", "/admin/settings"],
  ["//evil.example/x", "/admin"],
  ["https://evil.example/", "/admin"],
  ['/\\evil.example', "/admin"],
  ["/admin\nX-Bad: 1", "/admin"],
  ["", "/admin"],
];
foreach ($cases as [$in, $want]) {
  $got = \RetroBB\Core\Guard::localPath($in, "/admin");
  if ($got !== $want) {
    fwrite(STDERR, "localPath(" . json_encode($in) . ") = " . json_encode($got) . "\n");
    $bad++;
  }
}
exit($bad ? 1 : 0);
'''
r = subprocess.run(['docker', 'run', '--rm', '-v', ROOT + ':/app:ro', '-w', '/app',
                    'retrobb-php', 'php', '-r', php],
                   capture_output=True, text=True)
check('return path follows only local paths', r.returncode == 0,
      (r.stdout + r.stderr).strip()[:200])
finish()
