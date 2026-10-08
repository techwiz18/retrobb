import re, sqlite3, urllib.request, urllib.parse, http.cookiejar
base = 'http://localhost:8080'
db = '/media/dan/data/ai/RandomProjects/retrobb/storage/retrobb.sqlite'

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    url = path if path.startswith('http') else base + path
    r = op.open(url)
    return r.url, r.read().decode()

def views_of(tid):
    con = sqlite3.connect(db)
    v = con.execute('SELECT views FROM topics WHERE id=?', (tid,)).fetchone()[0]
    con.close()
    return v

def post(op, path, fields):
    data = urllib.parse.urlencode(fields, doseq=True).encode()
    req = urllib.request.Request(base + path, data=data)
    r = op.open(req)
    return r.url, r.read().decode()

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

admin = session()
_, html = get(admin, '/login')
post(admin, '/login', {'csrf': csrf(html), 'login': 'admin', 'password': 'password123' if False else 'admin123', 'next': '/'})
_, html = get(admin, '/admin')
post(admin, '/admin/settings', {'csrf': csrf(html), 'flood_seconds': '0'})
_, html = get(admin, '/new-topic/2')
url, nhtml = post(admin, '/new-topic/2', {'csrf': csrf(html), 'title': 'V23 probe final', 'body': 'probe body here'})
print('new-topic url:', url)
if '.t' not in url:
    print('form snippet:', nhtml[nhtml.find('flash-error')-20:nhtml.find('flash-error')+200] if 'flash-error' in nhtml else nhtml[:300])
tid = int(re.search(r'\.t(\d+)', url).group(1))
s = session()
get(s, url)
a = views_of(tid)
get(s, url)
b = views_of(tid)
s2 = session()
get(s2, url)
c = views_of(tid)
print('db views after 1st, 2nd same-session, 3rd new-session:', a, b, c, '-> ok:', b == a and c == a + 1)
