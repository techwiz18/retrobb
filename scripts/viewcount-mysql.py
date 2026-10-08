import re, urllib.request, urllib.parse, http.cookiejar, os
base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def get(op, path):
    url = path if path.startswith('http') else base + path
    r = op.open(url)
    return r.url, r.read().decode()

def post(op, path, fields):
    data = urllib.parse.urlencode(fields, doseq=True).encode()
    req = urllib.request.Request(base + path, data=data)
    r = op.open(req)
    return r.url, r.read().decode()

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

def views(html):
    return int(re.search(r'(\d+) views', html).group(1))

admin = session()
login(admin, 'admin', 'admin123')
_, html = get(admin, '/admin/settings')
_, cur = get(admin, '/admin')
post(admin, '/admin/settings', {'csrf': csrf(html), 'flood_seconds': '0', 'return': 'settings'})
_, html = get(admin, '/new-topic/2')
url, _ = post(admin, '/new-topic/2', {'csrf': csrf(html), 'title': 'Quiet views probe', 'body': 'counting views here'})
s1 = session()
_, h1 = get(s1, url)
_, h2 = get(s1, url)
s2 = session()
_, h3 = get(s2, url)
v1, v2, v3 = views(h1), views(h2), views(h3)
print('views:', v1, v2, v3, '-> once-per-session:', v1 == v2 and v3 == v2 + 1)
