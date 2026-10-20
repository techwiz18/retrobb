"""Verify the stored-HTML repair (bin/rebuild-html.php / HtmlRepair).

Simulates pre-fix rows directly in the test database: an injected bare-[url]
payload, an entity-obfuscated javascript: link, a PM payload, a ghost "moved"
stub, HTML with no BBCode source, and prose that merely mentions onerror= in
text. Then runs the CLI and checks it neutralises only the injected rows,
preserves mention links (models/User.php must be loaded), and never clobbers
ghosts, empty sources or prose.
"""
import os, re, subprocess, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, note, finish

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.environ.get('RETROBB_TEST_DB', 'retrobb_test')
MARK = 'rebuildcheck-%d' % os.getpid()

def sql(q):
    r = subprocess.run(['sh', 'scripts/mysql-sql.sh', q], capture_output=True, text=True,
                       cwd=ROOT, env=dict(os.environ, RETROBB_TEST_DB=DB))
    return r.stdout.strip()

def rebuild(*args):
    cfg = os.path.join(ROOT, 'storage', 'test-config.php')
    r = subprocess.run(
        ['docker', 'run', '--rm', '-v', ROOT + ':/app', '-v', cfg + ':/app/config.php',
         '-w', '/app', '--network', 'retrobb-net', 'retrobb-php',
         'php', 'bin/rebuild-html.php', *args],
        capture_output=True, text=True)
    return r.returncode, (r.stdout or '') + (r.stderr or '')

def q(s):
    return s.replace("'", "''")

post_ids, pm_ids = [], []
try:
    # --- fixtures (bypass the renderer to recreate pre-fix stored HTML) -----
    unsafe_bb = '%s @admin [url]http://x/</a><script>alert(1)</script>[/url]' % MARK
    unsafe_html = ('<p>%s @admin <a href="http://x/">http://x/</a>'
                   '<script>alert(1)</script></a></p>' % MARK)
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'%s','%s',NOW())" % (q(unsafe_bb), q(unsafe_html)))
    unsafe_id = int(sql("SELECT id FROM posts WHERE body_bbcode LIKE '%%%s%%' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(unsafe_id)

    # entity-obfuscated javascript: scheme -> must be detected
    enc_bb = '%s [url]java&#115;cript:alert(1)[/url]' % MARK
    enc_html = '<p>%s <a href="java&#115;cript:alert(1)">x</a></p>' % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'%s','%s',NOW())" % (q(enc_bb), q(enc_html)))
    enc_id = int(sql("SELECT id FROM posts WHERE body_bbcode LIKE '%%java&#115;cript%%' ORDER BY id DESC LIMIT 1"))
    post_ids.append(enc_id)

    # prose mentioning an event handler in text is not suspicious
    prose_html = '<p>%s the onerror= trick only bites inside a tag</p>' % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'%s','%s',NOW())" % (MARK, q(prose_html)))
    prose_id = int(sql("SELECT id FROM posts WHERE body_bbcode='%s' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(prose_id)

    ghost_html = '<p>Moved: <a href="/topic/x.t2">%s</a></p>' % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'moved-stub','%s',NOW())" % q(ghost_html))
    ghost_id = int(sql("SELECT id FROM posts WHERE body_html LIKE '%%Moved: %%' AND body_html LIKE '%%%s%%' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(ghost_id)

    empty_html = '<p>legacy-html-%s</p>' % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'','%s',NOW())" % q(empty_html))
    empty_id = int(sql("SELECT id FROM posts WHERE body_html LIKE '%%legacy-html-%s%%' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(empty_id)

    # A `>` inside a quoted attribute must not hide a later dangerous attr.
    qgt_bb = '%s [url]javascript:alert(1)[/url]' % MARK
    qgt_html = '<p>%s <a title=">" href="javascript:alert(1)">x</a></p>' % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'%s','%s',NOW())" % (q(qgt_bb), q(qgt_html)))
    qgt_id = int(sql("SELECT id FROM posts WHERE body_bbcode LIKE '%%[url]javascript%%' AND body_bbcode LIKE '%%%s%%' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(qgt_id)

    # Unterminated tag at EOF (no closing `>`).
    eof_bb = '%s [img]x[/img]' % MARK
    eof_html = '<p>%s</p><img src=x onerror=alert(1)' % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'%s','%s',NOW())" % (q(eof_bb), q(eof_html)))
    eof_id = int(sql("SELECT id FROM posts WHERE body_html LIKE '%%onerror=alert(1)' AND body_html LIKE '%%%s%%' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(eof_id)

    # A quote inside an unquoted attribute value must not hide the next attr.
    unq_dq_bb = '%s [img]x[/img]' % MARK
    unq_dq_html = '<p>%s</p><img src=x" onerror=alert(1)>' % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'%s','%s',NOW())" % (q(unq_dq_bb), q(unq_dq_html)))
    unq_dq_id = int(sql("SELECT id FROM posts WHERE body_html LIKE '%%src=x\" onerror%%' AND body_html LIKE '%%%s%%' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(unq_dq_id)

    unq_sq_bb = '%s [url]http://x/[/url]' % MARK
    unq_sq_html = "<p>%s</p><a href=https://x/' onmouseover=alert(1)>x</a>" % MARK
    sql("INSERT INTO posts (topic_id,user_id,body_bbcode,body_html,created_at) VALUES "
        "(1,1,'%s','%s',NOW())" % (q(unq_sq_bb), q(unq_sq_html)))
    unq_sq_id = int(sql("SELECT id FROM posts WHERE body_html LIKE '%%onmouseover%%' AND body_html LIKE '%%%s%%' ORDER BY id DESC LIMIT 1" % MARK))
    post_ids.append(unq_sq_id)

    pm_html = '<p>%s <script>alert(2)</script></p>' % MARK
    sql("INSERT INTO pms (sender_id,recipient_id,subject,body_bbcode,body_html,created_at) VALUES "
        "(1,2,'%s','%s','%s',NOW())" % (MARK, q(pm_html), q(pm_html)))
    pm_id = int(sql("SELECT id FROM pms WHERE subject='%s' ORDER BY id DESC LIMIT 1" % MARK))
    pm_ids.append(pm_id)

    check('fixtures inserted', all(i > 0 for i in (unsafe_id, enc_id, prose_id, ghost_id, empty_id, qgt_id, eof_id, unq_dq_id, unq_sq_id, pm_id)))

    # --- dry run writes nothing --------------------------------------------
    before = sql("SELECT body_html FROM posts WHERE id=%d" % unsafe_id)
    code, out = rebuild('--dry-run')
    check('dry-run exits 0', code == 0, code)
    check('dry-run leaves injected HTML untouched',
          sql("SELECT body_html FROM posts WHERE id=%d" % unsafe_id) == before)
    check('dry-run leaves the PM untouched',
          '<script>' in sql("SELECT body_html FROM pms WHERE id=%d" % pm_id))

    # --- targeted run ------------------------------------------------------
    code, out = rebuild()
    m = re.search(r'rebuilt (\d+)', out)
    check('rebuild exits 0', code == 0, out.strip().splitlines()[-1:])
    check('targeted run rebuilt something', bool(m) and int(m.group(1)) >= 1, out.strip().splitlines()[-1:])
    after = sql("SELECT body_html FROM posts WHERE id=%d" % unsafe_id)
    check('injected script removed', '<script>' not in after, after[:100])
    check('mention link preserved (User model loaded)', '/members/admin.u1' in after, after[:120])
    enc_after = sql("SELECT body_html FROM posts WHERE id=%d" % enc_id)
    check('entity-obfuscated javascript: repaired',
          '<a ' not in enc_after.lower() and 'href=' not in enc_after.lower(), enc_after[:120])
    qgt_after = sql("SELECT body_html FROM posts WHERE id=%d" % qgt_id)
    check('quoted-">" attribute repaired',
          '<a ' not in qgt_after.lower() and 'href=' not in qgt_after.lower(), qgt_after[:120])
    eof_after = sql("SELECT body_html FROM posts WHERE id=%d" % eof_id)
    check('unterminated EOF tag repaired', 'onerror' not in eof_after.lower(), eof_after[:120])
    dq_after = sql("SELECT body_html FROM posts WHERE id=%d" % unq_dq_id)
    check('quote-in-unquoted value (double) repaired', 'onerror' not in dq_after.lower(), dq_after[:120])
    sq_after = sql("SELECT body_html FROM posts WHERE id=%d" % unq_sq_id)
    check('quote-in-unquoted value (single) repaired', 'onmouseover' not in sq_after.lower(), sq_after[:120])
    check('PM payload repaired', '<script>' not in sql("SELECT body_html FROM pms WHERE id=%d" % pm_id))
    check('ghost stub untouched', sql("SELECT body_html FROM posts WHERE id=%d" % ghost_id) == ghost_html)
    check('empty-bbcode HTML untouched', sql("SELECT body_html FROM posts WHERE id=%d" % empty_id) == empty_html)
    check('prose with onerror= text untouched', sql("SELECT body_html FROM posts WHERE id=%d" % prose_id) == prose_html)

    # --- --all: ghosts/empty still skipped; mentions stay links ------------
    code, out = rebuild('--all')
    check('--all exits 0', code == 0, code)
    check('--all leaves ghost stub intact', sql("SELECT body_html FROM posts WHERE id=%d" % ghost_id) == ghost_html)
    check('--all leaves empty-bbcode HTML intact', sql("SELECT body_html FROM posts WHERE id=%d" % empty_id) == empty_html)
    check('--all keeps mention links', '/members/admin.u1' in sql("SELECT body_html FROM posts WHERE id=%d" % unsafe_id))

    # --- idempotent --------------------------------------------------------
    before2 = sql("SELECT body_html FROM posts WHERE id=%d" % unsafe_id)
    rebuild('--all')
    check('second run is a no-op for the fixed row',
          sql("SELECT body_html FROM posts WHERE id=%d" % unsafe_id) == before2)
finally:
    for i in post_ids:
        sql("DELETE FROM posts WHERE id=%d" % i)
    for i in pm_ids:
        sql("DELETE FROM pms WHERE id=%d" % i)

finish()
