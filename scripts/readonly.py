import os, sys, urllib.request, urllib.error
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from _harness import check, finish

base = os.environ.get('RETROBB_TEST_BASE', 'http://localhost:8080')

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

for path in ['/', '/forum/general-chat.f2', '/topic/what-was-your-first-forum.t2',
             '/login', '/register', '/members', '/sitemap.xml']:
    check('GET %s is 200' % path, status(path)[0] == 200, status(path)[0])

# ?page=N is not the canonical route — it 301s to /page-N
check('topic ?page=2 canonicalizes (301)', status('/topic/what-was-your-first-forum.t2?page=2')[0] == 301,
      status('/topic/what-was-your-first-forum.t2?page=2'))
check('logged-out /admin is 403', status('/admin')[0] == 403, status('/admin')[0])
check('bad topic slug 301s to canonical', status('/topic/nope.t1')[0] == 301, status('/topic/nope.t1'))
check('bad member id 404s', status('/members/nope.u999')[0] == 404)
check('unknown route 404s', status('/nope')[0] == 404)
finish()
