"""Verify the PostBody seam (issue #40).

Rendering, display fallback and the paired writes all live in one module, so
this suite drives that module directly against the test database and then
inspects stored rows. Covers the renderer contract (escaping, link allowlist,
mentions, plugin filter), display repair of legacy HTML, and that insert/update
write body_bbcode and body_html together (update also stamps edited_at).
"""
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CFG = os.path.join(ROOT, 'storage', 'test-config.php')

PHP = r'''<?php
declare(strict_types=1);
$root = '/app';
require_once $root . '/core/helpers.php';
require_once $root . '/core/Db.php';
require_once $root . '/core/Slug.php';
require_once $root . '/core/Hooks.php';
require_once $root . '/core/BBCode.php';
require_once $root . '/core/Mentions.php';
require_once $root . '/core/PostBody.php';
require_once $root . '/core/Auth.php';
require_once $root . '/models/User.php';

use RetroBB\Core\Hooks;
use RetroBB\Core\PostBody;

function t(string $name, bool $ok, string $detail = ''): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name;
    if ($detail !== '') {
        echo ' — ' . $detail;
    }
    echo "\n";
    if (!$ok) {
        $GLOBALS['failed'] = true;
    }
}

$GLOBALS['failed'] = false;

// renderer contract: escaping, link allowlist, mentions
$bb = '[b]bold[/b] <script>x</script> [url=javascript:alert(1)]bad[/url] @admin';
$html = PostBody::render($bb);
t('render keeps bbcode tags', str_contains($html, '<strong>bold</strong>'));
t('render escapes raw tags', str_contains($html, '&lt;script&gt;'));
t('render blocks javascript: links', !str_contains($html, 'href="javascript:'));
t('render links mentions', str_contains($html, '/members/admin.u1') && str_contains($html, '>admin</a>'));

// plugin filter applies to rendered HTML
Hooks::add_filter('post_body_html', fn($h) => $h . '<!--postbodycheck-->');
t('render applies plugin filter', str_contains(PostBody::render('[b]x[/b]'), '<!--postbodycheck-->'));

// display: trustworthy HTML passes through untouched
t('display keeps good stored html', PostBody::display('[b]different[/b]', '<p>kept</p>') === '<p>kept</p>');

// display: legacy HTML (leftover bbcode) is re-rendered, filter included
$legacy = PostBody::display('[quote]old[/quote]', '<p>[quote]old[/quote]</p>');
t('display repairs legacy html', str_contains($legacy, '<blockquote') && str_contains($legacy, '<!--postbodycheck-->'));

// paired writes
$pdo = RetroBB\Core\Db::pdo();
$pid = PostBody::insert(1, 1, '[b]insert[/b]', '2026-01-01 00:00:00');
$row = $pdo->query('SELECT body_bbcode, body_html, edited_at FROM posts WHERE id=' . $pid)->fetch();
t('insert writes bbcode', $row['body_bbcode'] === '[b]insert[/b]');
t('insert writes matching html', $row['body_html'] === PostBody::render('[b]insert[/b]'));
t('insert leaves edited_at null', $row['edited_at'] === null);

PostBody::update($pid, '[i]updated[/i]');
$row = $pdo->query('SELECT body_bbcode, body_html, edited_at FROM posts WHERE id=' . $pid)->fetch();
t('update writes bbcode', $row['body_bbcode'] === '[i]updated[/i]');
t('update writes matching html', $row['body_html'] === PostBody::render('[i]updated[/i]'));
t('update stamps edited_at', !empty($row['edited_at']));

$pdo->prepare('DELETE FROM posts WHERE id=?')->execute([$pid]);

// import-shaped insert: caller's created_at is kept and the html is what the
// old direct BBCode::toHtml write produced, so imported posts look unchanged.
$imported = '[quote=OldUser]hello[/quote]' . "\n\n" . '[url=https://example.com]site[/url]';
$iid = PostBody::insert(1, 1, $imported, '2005-01-02 03:04:05');
$row = $pdo->query('SELECT body_bbcode, body_html, created_at FROM posts WHERE id=' . $iid)->fetch();
t('import insert keeps created_at', $row['created_at'] === '2005-01-02 03:04:05');
t('import insert renders like the old writer', $row['body_html'] === \RetroBB\Core\BBCode::toHtml($imported));
$pdo->prepare('DELETE FROM posts WHERE id=?')->execute([$iid]);

// private message compose writes both forms through the module
require_once $root . '/models/Pm.php';
$pmBb = '[b]pm[/b] <script>bad</script>';
$res = \RetroBB\Models\Pm::send(1, 2, 'postbodycheck', $pmBb);
t('pm send accepted', $res['ok'] === true, $res['error'] ?? '');
if ($res['ok']) {
    $row = $pdo->query('SELECT body_bbcode, body_html FROM pms WHERE id=' . (int) $res['id'])->fetch();
    t('pm stores bbcode', $row['body_bbcode'] === $pmBb);
    t('pm stores module html', $row['body_html'] === PostBody::render($pmBb));
    t('pm html escapes', str_contains($row['body_html'], '&lt;script&gt;'));
    $pdo->prepare('DELETE FROM pms WHERE id=?')->execute([(int) $res['id']]);
}

exit($GLOBALS['failed'] ? 1 : 0);
'''

def source(rel: str) -> str:
    with open(os.path.join(ROOT, rel), encoding='utf-8') as fh:
        return fh.read()


if not os.path.isfile(CFG):
    check('test config exists', False, CFG)
    finish()

# Every remaining writer/reader goes through the module, and no view decides
# whether stored html is trustworthy.
pm_view = source('views/pm/show.php')
check('pm view renders through PostBody', 'PostBody::display' in pm_view)
check('pm view does not call the repair check', 'needsRepair' not in pm_view)
pm_model = source('models/Pm.php')
check('pm compose renders through PostBody', 'PostBody::render' in pm_model and 'BBCode::toHtml' not in pm_model)
importer = source('controllers/ImportController.php')
check('importer writes through PostBody', 'PostBody::insert' in importer and 'BBCode::toHtml' not in importer)
topic_model = source('models/Topic.php')
check('topic model renders its stub through PostBody',
      'PostBody::render' in topic_model and 'BBCode::toHtml' not in topic_model)

proc = subprocess.run(
    ['docker', 'run', '--rm', '-i', '-v', ROOT + ':/app', '-v', CFG + ':/app/config.php',
     '-w', '/app', '--network', 'retrobb-net', 'retrobb-php', 'php'],
    input=PHP, capture_output=True, text=True,
)
out = (proc.stdout or '') + (proc.stderr or '')
seen = 0
for line in out.splitlines():
    if line.startswith('PASS ') or line.startswith('FAIL '):
        seen += 1
        check(line[5:], line.startswith('PASS '))
if seen == 0:
    check('php check ran', False, out.strip()[-500:])
    finish()
check('php check exited clean', proc.returncode == 0, 'exit %d' % proc.returncode)
finish()
