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

# --- settings registry contract (issue #45) ------------------------------
def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def field(html, name):
    m = re.search(r'name="%s"[^>]*value="([^"]*)"' % re.escape(name), html)
    return m.group(1) if m else None

def settings_field(name):
    return field(op.open(base + '/admin/settings').read().decode(), name)

def submit(form_page, path, fields):
    page = op.open(base + form_page).read().decode()
    data = dict(fields, csrf=csrf(page))
    return op.open(urllib.request.Request(base + path, data=urllib.parse.urlencode(data).encode())).read().decode()

def save_settings(fields):
    return submit('/admin/settings', '/admin/settings', fields)

# Each page writes only the keys it owns: a POST aimed at spam/features
# must not be able to carry another page's key along.
save_settings({'return': 'settings', 'board_name': 'Registry Probe', 'board_tagline': 'probe'})
save_settings({'return': 'spam', 'board_name': 'Hijacked', 'captcha_provider': 'honeypot'})
check('spam save cannot alter the board name', settings_field('board_name') == 'Registry Probe', settings_field('board_name'))
submit('/admin/features', '/admin/features', {'board_name': 'Hijacked'})
check('features save cannot alter the board name', settings_field('board_name') == 'Registry Probe', settings_field('board_name'))
# A features POST with no checkboxes turns them all off (existing behaviour);
# put the seeded defaults back so later suites see a pristine board.
submit('/admin/features', '/admin/features', {
    'feature_alerts': '1', 'feature_mentions': '1', 'feature_reactions': '1', 'feature_pms': '1',
    'feature_avatars_upload': '1', 'feature_avatars_link': '1', 'skin_selector': '1',
    'default_skin': 'classic', 'default_theme': 'auto',
    'theme_light': '1', 'theme_dark': '1', 'theme_auto': '1'})

# Validation comes from the definition: clamps, truncation, allowlists.
save_settings({'return': 'settings', 'posts_per_page': '9999', 'board_tagline': 'x' * 250})
check('settings save clamps through the registry', settings_field('posts_per_page') == '50', settings_field('posts_per_page'))
check('settings save truncates through the registry', len(settings_field('board_tagline') or '') == 200, len(settings_field('board_tagline') or ''))
submit('/admin/spam', '/admin/settings', {'return': 'spam', 'captcha_provider': 'builtin'})
submit('/admin/spam', '/admin/settings', {'return': 'spam', 'captcha_provider': 'bogus'})
spam_html = op.open(base + '/admin/spam').read().decode()
check('spam save rejects a provider outside the registry allowlist',
      re.search(r'<option value="builtin" selected>', spam_html) is not None)

# Restore the fixture values so later suites see a pristine board.
save_settings({'return': 'settings', 'board_name': 'RetroBB', 'board_tagline': 'An old-school forum for the modern web', 'posts_per_page': '15'})
submit('/admin/spam', '/admin/settings', {'return': 'spam', 'captcha_provider': 'honeypot'})
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

# --- AdminCP splits by area (issue #47) ----------------------------------
# The 28-action class is gone: each area owns one module, the route table
# resolves to it, and no module grows back into a god class. Behaviour is
# covered by the page checks above.
index_src = open(os.path.join(ROOT, 'public', 'index.php')).read()
routes = {}
for line in index_src.splitlines():
    if '/admin' not in line or 'Controllers\\' not in line:
        continue
    m = re.search(r"Controllers\\([\w\\]+)::class, '(\w+)'", line)
    if m:
        routes.setdefault(m.group(1), set()).add(m.group(2))

EXPECTED = {
    'Admin\\StructureAdminController': {
        'structurePage', 'addForum', 'addCategory', 'moveCategory', 'moveForum',
        'editCategoryForm', 'renameCategory', 'deleteCategory', 'editForumForm',
        'renameForum', 'deleteForum',
    },
    'Admin\\UsersAdminController': {
        'usersPage', 'setGroup', 'superToggle', 'bansPage', 'unban',
    },
    'Admin\\PluginsAdminController': {
        'pluginsPage', 'pluginToggle', 'pluginUninstall',
    },
    'Admin\\MaintenanceAdminController': {
        'index', 'settingsPage', 'featuresPage', 'spamPage', 'saveSettings',
        'saveFeatures', 'modlogPage', 'repairHtml', 'runMigrate',
    },
}

sources = {}
for cls, actions in EXPECTED.items():
    path = os.path.join(ROOT, 'controllers', 'Admin', cls.split('\\')[-1] + '.php')
    src = open(path).read() if os.path.isfile(path) else ''
    sources[cls] = src
    public = set(re.findall(r'public function (\w+)\(', src))
    check('%s exists with its routes' % cls,
          src != '' and routes.get(cls) == actions,
          'routes: %s' % sorted(routes.get(cls, set())))
    check('%s declares every routed action' % cls, actions <= public,
          sorted(actions - public))
    check('%s exposes fewer than 20 actions' % cls, 0 < len(public) < 20, len(public))

check('no catch-all AdminController remains',
      'AdminController' not in routes
      and not os.path.isfile(os.path.join(ROOT, 'controllers', 'AdminController.php')),
      sorted(routes.get('AdminController', set())))

# Other /admin routes keep their shared controllers (import + mod queue).
check('shared admin routes stay on their own controllers',
      set(routes) - set(EXPECTED) <= {'ImportController', 'ReportController'},
      sorted(set(routes) - set(EXPECTED)))

# Each area's writes stay in its module: a structure change cannot need the
# users module, and plugin/settings work goes through the settled owners.
struct = sources['Admin\\StructureAdminController']
users = sources['Admin\\UsersAdminController']
plugins = sources['Admin\\PluginsAdminController']
maint = sources['Admin\\MaintenanceAdminController']

def only_in(needle, owner):
    return needle in sources[owner] and all(needle not in s for c, s in sources.items() if c != owner)

check('structure writes live only in the structure module',
      only_in('INSERT INTO forums', 'Admin\\StructureAdminController')
      and only_in('INSERT INTO categories', 'Admin\\StructureAdminController'))
check('user tier writes live only in the users module',
      only_in('User::setGroup', 'Admin\\UsersAdminController')
      and only_in('User::setSuper', 'Admin\\UsersAdminController'))
check('plugin lifecycle calls go through core Plugins',
      only_in('Plugins::', 'Admin\\PluginsAdminController') and 'Plugins::toggle' in plugins
      and 'Plugins::loadOne' in plugins)
check('settings writes go through core Settings',
      only_in('Settings::forPage', 'Admin\\MaintenanceAdminController') and 'Settings::coerce' in maint)
finish()
