import re, urllib.request, urllib.parse, http.cookiejar, os, subprocess
base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None

cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
opNR = urllib.request.build_opener(NoRedir, urllib.request.HTTPCookieProcessor(cj))

def sql(q):
    out = subprocess.run(['sh', 'scripts/mysql-sql.sh', q],
                         capture_output=True, text=True, cwd='/media/dan/data/ai/RetroBB')
    return out.stdout.strip()

def get(path):
    r = op.open(base + path)
    return r.url, r.read().decode()

def noreq(path, fields=None):
    data = urllib.parse.urlencode(fields or {}, doseq=True).encode() if fields is not None else None
    try:
        r = opNR.open(urllib.request.Request(base + path, data=data))
        return r.status, r.headers.get('Location')
    except Exception as e:
        return getattr(e, 'code', '?'), (e.headers.get('Location') if hasattr(e, 'headers') else None)

html = op.open(base + '/login').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
op.open(urllib.request.Request(base + '/login', data=urllib.parse.urlencode({'csrf': tok, 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()))
html = op.open(base + '/new-topic/2').read().decode()
tok = re.search(r'name="csrf" value="([^"]+)"', html).group(1)
code, loc = noreq('/new-topic/2', {'csrf': tok, 'title': 'Nofollow probe', 'body': 'counting strictly here'})
print('POST:', code, '->', loc)
tid = int(re.search(r'\.t(\d+)', loc).group(1))
print('db right after bare POST:', sql('SELECT views FROM topics WHERE id=%d' % tid))
get('/topic/nofollow-probe.t%d' % tid)
print('after 1st GET:', sql('SELECT views FROM topics WHERE id=%d' % tid))
get('/topic/nofollow-probe.t%d' % tid)
print('after 2nd GET:', sql('SELECT views FROM topics WHERE id=%d' % tid))
