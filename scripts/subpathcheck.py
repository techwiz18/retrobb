"""Subpath deployment (issue #43).

One implementation of the base prefix: Router::stripBase() decides whether a
request path is under the prefix and what it is without it. A board served
under /forum keeps routing, keeps its canonical redirects, and never doubles
the prefix; the same board at the domain root is unchanged.
"""
import json
import os, subprocess, sys, time, urllib.error, urllib.request
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8081')
DB = os.environ.get('RETROBB_TEST_DB', 'retrobb_test')
NAME = os.environ.get('RETROBB_TEST_NAME', 'retrobb-test')
CFG = os.path.join(ROOT, 'storage', 'test-config.php')
PREFIX = '/forum'


def sql(q):
    r = subprocess.run(['sh', 'scripts/mysql-sql.sh', q], capture_output=True, text=True,
                       cwd=ROOT, env=dict(os.environ, RETROBB_TEST_DB=DB))
    return r.stdout.strip()


def php(snippet, env=None):
    """Run PHP in the test container with an optional env override."""
    args = ['docker', 'exec']
    for k, v in (env or {}).items():
        args += ['-e', '%s=%s' % (k, v)]
    args += [NAME, 'php', '-r', snippet]
    r = subprocess.run(args, capture_output=True, text=True)
    return (r.stdout or '').strip(), (r.stderr or '').strip(), r.returncode


class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None


def get(base, path):
    op = urllib.request.build_opener(NoRedir)
    try:
        r = op.open(base + path)
        return r.code, r.headers.get('Location'), r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location'), e.read().decode()


def start_sub_board():
    name = NAME + '-sub'
    subprocess.run(['docker', 'rm', '-f', name], capture_output=True)
    args = ['docker', 'run', '-d', '--rm', '--name', name, '-p', '0:8000',
            '-e', 'RETROBB_BASE_PATH=' + PREFIX,
            '-v', ROOT + ':/app', '-w', '/app', '--network', 'retrobb-net']
    if os.path.isfile(CFG):
        args += ['-v', CFG + ':/app/config.php']
    args += ['retrobb-php', 'php', '-S', '0.0.0.0:8000', '-t', 'public', 'public/router.php']
    r = subprocess.run(args, capture_output=True, text=True)
    if r.returncode != 0:
        return name, None
    out = subprocess.run(['docker', 'port', name, '8000'], capture_output=True, text=True).stdout
    port = out.strip().splitlines()[0].rsplit(':', 1)[-1] if out.strip() else ''
    if not port:
        return name, None
    base = 'http://localhost:%s' % port
    for _ in range(20):
        try:
            if urllib.request.urlopen(base + PREFIX + '/login', timeout=2).code == 200:
                break
        except Exception:
            time.sleep(0.5)
    return name, base


forum = sql('SELECT id, slug FROM forums ORDER BY id LIMIT 1').split('\t')
topic = sql('SELECT id, slug FROM topics ORDER BY id LIMIT 1').split('\t')
if len(forum) < 2 or len(topic) < 2:
    print('FAIL no seeded forum/topic to test with')
    finish()
fid, fslug = forum[0], forum[1]
tid, tslug = topic[0], topic[1]

sub_name, sub = start_sub_board()
try:
    # --- the single prefix implementation ---------------------------------
    out, err, rc = php(
        'require "/app/core/helpers.php"; require "/app/core/Router.php"; '
        'echo json_encode(["members" => RetroBB\\Core\\Router::stripBase("/forum/members"), '
        '"root" => RetroBB\\Core\\Router::stripBase("/forum"), '
        '"outside" => RetroBB\\Core\\Router::stripBase("/members"), '
        '"sibling" => RetroBB\\Core\\Router::stripBase("/forums"), '
        '"trailing" => RetroBB\\Core\\Router::stripBase("/forum/")]);',
        env={'RETROBB_BASE_PATH': PREFIX})
    try:
        got = json.loads(out) if rc == 0 else {}
    except ValueError:
        got = {}
    check('router owns prefix stripping', got == {
        'members': '/members', 'root': '/', 'outside': None,
        'sibling': None, 'trailing': '/'}, out or err)
    out, err, rc = php(
        'require "/app/core/helpers.php"; require "/app/core/Router.php"; '
        '$_SERVER["REQUEST_URI"] = "/forum/topic/x.t1"; '
        'echo json_encode([is_canonical("/forum/topic/x.t1"), '
        'is_canonical("/topic/x.t1"), is_canonical("/forum/topic/y.t1")]);',
        env={'RETROBB_BASE_PATH': PREFIX})
    try:
        canon = json.loads(out) if rc == 0 else []
    except ValueError:
        canon = []
    check('canonical comparison uses the prefix owner',
          canon == [True, True, False], out or err)
    out, err, rc = php(
        'require "/app/core/helpers.php"; require "/app/core/Router.php"; '
        'echo canonical_url("/forum/topic/x.t1");',
        env={'RETROBB_BASE_PATH': PREFIX})
    check('canonical_url never doubles the prefix', out == '/forum/topic/x.t1', out or err)

    # --- root deployment is unchanged -------------------------------------
    code, loc, page = get(BASE, '/login')
    check('root: app route still 200', code == 200, '%s %s' % (code, loc))
    code, loc, _ = get(BASE, '/topic/%s.t%s' % (tslug, tid))
    check('root: topic route still 200', code == 200, '%s %s' % (code, loc))
    code, loc, _ = get(BASE, '/topic/wrong-%s.t%s' % (tslug, tid))
    check('root: canonical redirect has no prefix',
          code == 301 and loc == '/topic/%s.t%s' % (tslug, tid), '%s %s' % (code, loc))

    # --- subpath deployment ------------------------------------------------
    if sub is None:
        check('subpath board started', False, 'docker run failed for ' + sub_name)
    else:
        for label, path in [
            ('app route', PREFIX + '/login'),
            ('app route (stripped form)', '/login'),
            ('forum route', PREFIX + '/%s.f%s' % (fslug, fid)),
            ('topic route', PREFIX + '/topic/%s.t%s' % (tslug, tid)),
            ('topic route (proxy-stripped)', '/topic/%s.t%s' % (tslug, tid)),
            ('sitemap', PREFIX + '/sitemap.xml'),
        ]:
            code, loc, _ = get(sub, path)
            check('subpath: %s serves' % label, code == 200, '%s %s' % (code, loc))

        want = PREFIX + '/%s.f%s' % (fslug, fid)
        code, loc, _ = get(sub, PREFIX + '/wrong-%s.f%s' % (fslug, fid))
        check('subpath: forum canonical redirect not doubled', code == 301 and loc == want,
              '%s %s (want %s)' % (code, loc, want))
        code, loc, _ = get(sub, PREFIX + want)
        check('subpath: doubled prefix settles on one prefix', code == 301 and loc == want,
              '%s %s' % (code, loc))

        want = PREFIX + '/topic/%s.t%s' % (tslug, tid)
        code, loc, _ = get(sub, PREFIX + '/topic/wrong-%s.t%s' % (tslug, tid))
        check('subpath: topic canonical redirect not doubled', code == 301 and loc == want,
              '%s %s (want %s)' % (code, loc, want))

        code, _, page = get(sub, want)
        check('subpath: canonical tag carries one prefix',
              code == 200 and ('href="%s"' % want) in page, want)
finally:
    subprocess.run(['docker', 'rm', '-f', sub_name], capture_output=True)

finish()
