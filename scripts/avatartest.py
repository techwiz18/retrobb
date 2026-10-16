"""Avatar upload/link/remove flows (wipe only). Pure-stdlib PNG builder."""
import re, io, struct, zlib, random, mimetypes
import urllib.request, urllib.parse, http.cookiejar, urllib.error

base = 'http://localhost:8080'

def png(w, h, rnd=False):
    raw = b''.join(b'\x00' + (bytes(random.getrandbits(8) for _ in range(w * 3)) if rnd
                              else b'\x89\x9e\xda' * w) for _ in range(h))
    def chunk(t, d):
        c = t + d
        return struct.pack('>I', len(d)) + c + struct.pack('>I', zlib.crc32(c) & 0xffffffff)
    return (b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0))
            + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b''))

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def csrf(html):
    return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

def login(op, u, p):
    html = op.open(base + '/login').read().decode()
    op.open(urllib.request.Request(base + '/login',
            data=urllib.parse.urlencode({'csrf': csrf(html), 'login': u, 'password': p}).encode()))

def multipart(fields, files):
    bound = 'BOUNDARY42'
    body = b''
    for k, v in fields.items():
        body += f'--{bound}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode()
    for k, (fn, data, ct) in files.items():
        body += (f'--{bound}\r\nContent-Disposition: form-data; name="{k}"; filename="{fn}"\r\n'
                 f'Content-Type: {ct}\r\n\r\n').encode() + data + b'\r\n'
    return body + f'--{bound}--\r\n'.encode(), bound

def avatar_post(op, kind, url='', filedata=None, filename='a.png'):
    html = op.open(base + '/settings/profile').read().decode()
    fields = {'csrf': csrf(html), 'form': 'avatar', 'avatar_kind': kind, 'avatar_url': url}
    files = {'avatar': (filename, filedata, 'image/png')} if filedata else {}
    body, bound = multipart(fields, files)
    req = urllib.request.Request(base + '/settings/profile', data=body,
                                 headers={'Content-Type': 'multipart/form-data; boundary=' + bound})
    try:
        return op.open(req).read().decode(), 200
    except urllib.error.HTTPError as e:
        return e.read().decode() if hasattr(e, 'read') else '', e.code

mem, admin = session(), session()
login(mem, 'TechWiz18', 'password123')
login(admin, 'admin', 'admin123')

# guest bounced to login (302 -> /login, followed by urllib)
class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None
g = urllib.request.build_opener(NoRedir)
try:
    g.open(urllib.request.Request(base + '/settings/profile', data=b'x=1'))
    print('guest post: OPEN (bad)')
except urllib.error.HTTPError as e:
    print('guest post:', e.code, e.headers.get('Location'))

b, _ = avatar_post(mem, 'upload', filedata=png(100, 100))
print('valid upload ok:', 'avatarimg' in mem.open(base + '/members/techwiz18.u4').read().decode())
b, _ = avatar_post(mem, 'upload', filedata=png(500, 500))
print('oversize dims rejected:', 'max is' in b and '200' in b)
b, _ = avatar_post(mem, 'upload', filedata=png(400, 400, rnd=True))
print('oversize bytes rejected:', 'KB limit' in b)
b, _ = avatar_post(mem, 'upload', filedata=b'not an image', filename='x.txt')
print('bad mime rejected:', 'Only JPG' in b)
b, _ = avatar_post(mem, 'link', url='https://example.com/pic.png')
prof = mem.open(base + '/members/techwiz18.u4').read().decode()
print('link shows remote:', 'https://example.com/pic.png' in prof)
b, _ = avatar_post(mem, 'link', url='ftp://example.com/pic.png')
print('bad link rejected:', 'direct https link' in b)
b, _ = avatar_post(mem, 'none')
prof = mem.open(base + '/members/techwiz18.u4').read().decode()
print('removed back to initial:', 'avatarimg' not in prof)

# disable uploads as admin, expect refusal
html = admin.open(base + '/admin/features').read().decode()
admin.open(urllib.request.Request(base + '/admin/features', data=urllib.parse.urlencode(
    {'csrf': csrf(html), 'feature_alerts': '1', 'feature_mentions': '1', 'feature_reactions': '1',
     'feature_pms': '1', 'skin_selector': '1', 'theme_light': '1', 'theme_dark': '1', 'theme_auto': '1',
     'default_skin': 'classic', 'default_theme': 'auto'}).encode()))
b, _ = avatar_post(mem, 'upload', filedata=png(50, 50))
print('disabled upload refused:', 'disabled' in b)
html = admin.open(base + '/admin/features').read().decode()
admin.open(urllib.request.Request(base + '/admin/features', data=urllib.parse.urlencode(
    {'csrf': csrf(html), 'feature_alerts': '1', 'feature_mentions': '1', 'feature_reactions': '1',
     'feature_pms': '1', 'feature_avatars_upload': '1', 'feature_avatars_link': '1', 'skin_selector': '1',
     'theme_light': '1', 'theme_dark': '1', 'theme_auto': '1',
     'default_skin': 'classic', 'default_theme': 'auto'}).encode()))
print('toggles restored')
