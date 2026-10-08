import re, urllib.request, urllib.parse, http.cookiejar, subprocess, sys

which = sys.argv[1] if len(sys.argv) > 1 else '1'

def sh(q):
    out = subprocess.run(['docker', 'exec', 'retrobb-mysql', 'mysql', '-uroot', '-proot', '-N', '-B', '-e', q],
                         capture_output=True, text=True)
    return out.stdout.strip()

# cPanel simulation: pre-created db + user with rights ONLY on it (no CREATE)
sh("DROP DATABASE IF EXISTS cpuser_forum; CREATE DATABASE cpuser_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; DROP USER IF EXISTS 'limited'@'%'; CREATE USER 'limited'@'%' IDENTIFIED BY 'limited123'; GRANT ALL PRIVILEGES ON cpuser_forum.* TO 'limited'@'%'; FLUSH PRIVILEGES;")
sh("DROP DATABASE IF EXISTS autocreated;")
print('dbs staged')

def fresh_session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def install(base, db, user, pw, admin, label):
    op = fresh_session()
    form = {
        'mysql_host': 'retrobb-mysql', 'mysql_port': '3306', 'mysql_db': db,
        'mysql_user': user, 'mysql_pass': pw,
        'board_name': label, 'board_tagline': 't', 'board_url': '',
        'default_skin': 'classic',
        'admin_user': admin, 'admin_email': '%s@example.com' % admin,
        'admin_pass': 'password123', 'admin_pass2': 'password123',
    }
    r = op.open(urllib.request.Request(base + '/install.php', data=urllib.parse.urlencode(form).encode()))
    body = r.read().decode()
    ok = 'Welcome aboard' in body
    print(label, '-> installed:', ok)
    if not ok:
        m = re.findall(r'flash-error">(.*?)</div>', body)
        print('  errors:', m[:3])
    return ok

# NOTE: installer writes config.php, so run auto-create LAST is wrong order;
# run each path separately with the lock removed in between.
if which == '1':
    print('--- path 1: restricted user, pre-created db ---')
    install('http://localhost:8080', 'cpuser_forum', 'limited', 'limited123', 'cpowner', 'CPBoard')
else:
    print('--- path 2: privileged user, auto-create ---')
    install('http://localhost:8080', 'autocreated', 'retrobb', 'retrobb', 'autoowner', 'AutoBoard')
    print('dbs now:', sh("SHOW DATABASES;"))
