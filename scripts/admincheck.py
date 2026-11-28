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
finish()
