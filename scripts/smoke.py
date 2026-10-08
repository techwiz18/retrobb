import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
import os as _os; base = _os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')
html = op.open(base + '/login').read().decode()
m = re.search(r'name="csrf" value="([^"]+)"', html)
print('csrf found:', bool(m))
data = urllib.parse.urlencode({'csrf': m.group(1), 'login': 'admin', 'password': 'admin123', 'next': '/'}).encode()
req = urllib.request.Request(base + '/login', data=data)
resp = op.open(req)
print('login final url:', resp.url)
admin = op.open(base + '/admin').read().decode()
print('admin page ok:', 'AdminCP' in admin and 'Settings' in admin)

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None
op2 = urllib.request.build_opener(NoRedir)
try:
    op2.open(base + '/topic/wrong-slug.t1')
    print('canonical redirect: NONE (bad)')
except urllib.error.HTTPError as e:
    print('canonical redirect:', e.code, e.headers.get('Location'))

# reply flow as admin
html2 = op.open(base + '/topic/welcome-to-retrobb-read-this-first.t1').read().decode()
m2 = re.search(r'name="csrf" value="([^"]+)"', html2)
print('reply csrf found:', bool(m2))
rdata = urllib.parse.urlencode({'csrf': m2.group(1), 'body': 'Automated test reply [b]works[/b] :)'}).encode()
rreq = urllib.request.Request(base + '/topic/x.t1/reply', data=rdata)
rresp = op.open(rreq)
print('reply final url:', rresp.url)
check = op.open(base + '/topic/welcome-to-retrobb-read-this-first.t1').read().decode()
print('reply visible:', 'Automated test reply' in check)
