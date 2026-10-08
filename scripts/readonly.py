import urllib.request, urllib.error
base = 'http://localhost:8080'

class NoRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, hdrs, newurl):
        return None
op = urllib.request.build_opener(NoRedir)

def status(path):
    try:
        r = op.open(base + path)
        return r.code, r.headers.get('Location')
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location')

print('home:', status('/')[0])
print('forum:', status('/forum/general-chat.f2')[0])
print('topic:', status('/topic/what-was-your-first-forum.t2')[0])
print('topic ?page=2 ->', status('/topic/what-was-your-first-forum.t2?page=2'))
print('login page:', status('/login')[0])
print('register page:', status('/register')[0])
print('members:', status('/members')[0])
print('admin logged-out:', status('/admin'))
print('sitemap:', status('/sitemap.xml')[0])
print('bad slug 301:', status('/topic/nope.t1')[1])
print('members bad id 404:', status('/members/nope.u999')[0])
print('unknown route 404:', status('/nope')[0])
