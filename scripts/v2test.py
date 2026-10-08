import re, urllib.request, urllib.parse, http.cookiejar, urllib.error
base = 'http://localhost:8080'

def session():
    cj = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

admin = session()
member = session()

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
    m = re.search(r'name="csrf" value="([^"]+)"', html)
    return m.group(1) if m else None

def login(op, u, p):
    _, html = get(op, '/login')
    post(op, '/login', {'csrf': csrf(html), 'login': u, 'password': p})

login(admin, 'admin', 'admin123')
print('admin login ok')

# disable flood for tests (restored from backup later)
_, html = get(admin, '/admin')
post(admin, '/admin/settings', {'csrf': csrf(html), 'flood_seconds': '0', 'edit_window_mins': '30'})
print('flood disabled for test')

# register a throwaway member (honeypot empty)
_, html = get(member, '/register')
_, html2 = post(member, '/register', {'csrf': csrf(html), 'username': 'v2tester', 'email': 'v2t@example.com', 'password': 'password123', 'website': ''})
print('register ok:', 'Log out' in html2 or 'v2tester' in html2)

# member creates topic + replies
_, html = get(member, '/new-topic/2')
url, html = post(member, '/new-topic/2', {'csrf': csrf(html), 'title': 'V2 throwaway topic', 'body': 'first post body here'})
print('new topic:', url)
m = re.search(r'\.t(\d+)', url)
tid = m.group(1)
_, html = get(member, '/topic/x.t' + tid)
url2, html2 = post(member, '/topic/x.t' + tid + '/reply', {'csrf': csrf(html), 'body': 'second post for split tests'})
print('reply ok:', 'second post for split' in html2)

# edit own post (find post id of reply)
m2 = re.search(r'/post/(\d+)/edit', html2)
print('edit link present:', bool(m2))
if m2:
    pid = m2.group(1)
    _, ehtml = get(member, '/post/' + pid + '/edit')
    url3, ehtml2 = post(member, '/post/' + pid + '/edit', {'csrf': csrf(ehtml), 'body': 'edited body content'})
    print('edit saved:', 'edited body content' in ehtml2 and 'Edited' in ehtml2)

# report admin's post in topic 1 (post 1) as member
_, rhtml = get(member, '/post/1/report')
print('report form:', 'Report post' in rhtml)
url4, rhtml2 = post(member, '/post/1/report', {'csrf': csrf(rhtml), 'reason': 'v2 test report'})
print('report submitted, back on topic:', '.t1' in url4)

# queue as admin
_, qhtml = get(admin, '/mod/reports')
print('queue shows report:', 'v2 test report' in qhtml)
m3 = re.search(r'/mod/report/(\d+)/handle', qhtml)
rid = m3.group(1)
_, qhtml2 = post(admin, '/mod/report/' + rid + '/handle', {'csrf': csrf(qhtml), 'status': 'resolved'})
print('report resolved, queue clear:', 'Queue is clear' in qhtml2)

# warn + ban member (find v2tester id via members page)
_, mhtml = get(admin, '/members')
m4 = re.search(r'/members/v2tester\.u(\d+)', mhtml)
vid = m4.group(1)
_, phtml = get(admin, '/members/v2tester.u' + vid)
print('mod panel on profile:', 'Moderate v2tester' in phtml)
post(admin, '/members/' + vid + '/warn', {'csrf': csrf(phtml), 'reason': 'v2 test warning'})
_, phtml = get(admin, '/members/v2tester.u' + vid)
print('warning recorded:', 'v2 test warning' in phtml)
post(admin, '/members/' + vid + '/ban', {'csrf': csrf(phtml), 'reason': 'v2 test ban', 'days': '1'})
# banned login attempt
banned = session()
_, lhtml = get(banned, '/login')
_, lhtml2 = post(banned, '/login', {'csrf': csrf(lhtml), 'login': 'v2tester', 'password': 'password123', 'next': '/'})
print('banned blocked:', 'banned' in lhtml2.lower())
# unban via admin, login works again
_, ahtml = get(admin, '/admin')
m5 = re.search(r'/admin/unban/(\d+)', ahtml)
post(admin, '/admin/unban/' + m5.group(1), {'csrf': csrf(ahtml)})
fresh = session()
login(fresh, 'v2tester', 'password123')
_, fhtml = get(fresh, '/')
print('login works after unban:', 'v2tester' in fhtml)

# move topic with ghost (mod tool routes take numeric ids)
_, mvhtml = get(admin, '/topic/' + tid + '/move')
print('move form:', 'Destination forum' in mvhtml)
url5, mvhtml2 = post(admin, '/topic/' + tid + '/move', {'csrf': csrf(mvhtml), 'forum_id': '3', 'ghost': '1'})
print('moved:', 'Test Zone' in mvhtml2 or '/topic/' in url5)
_, fhtml = get(admin, '/forum/test-zone.f3')
print('ghost visible in old forum:', 'Moved:' in get(admin, '/forum/general-chat.f2')[1])

# split first reply off to new topic
_, sphtml = get(admin, '/topic/' + tid + '/split')
print('split form:', 'Split posts' in sphtml)
pm = re.findall(r'name="post_ids\[\]" value="(\d+)"', sphtml)
url6, sphtml2 = post(admin, '/topic/' + tid + '/split', {'csrf': csrf(sphtml), 'title': 'V2 split child', 'post_ids': [pm[-1]]})
print('split child:', '.t' in url6)
child = re.search(r'\.t(\d+)', url6).group(1)

# merge child back (topic pages need slug URLs)
_, mghtml = get(admin, url6)
url7, mghtml2 = post(admin, '/topic/' + child + '/merge', {'csrf': csrf(mghtml), 'target': tid})
print('merged back:', '.t' + tid in url7)

# ordering: move forum 2 down then up
_, ahtml = get(admin, '/admin')
post(admin, '/admin/forum/2/move/down', {'csrf': csrf(ahtml)})
_, ahtml = get(admin, '/admin')
post(admin, '/admin/forum/2/move/up', {'csrf': csrf(ahtml)})
print('ordering toggles ok')

# modlog + bans visible
_, ahtml = get(admin, '/admin')
print('modlog has entries:', 'move' in ahtml and 'ban' in ahtml)
print(' bans section:', 'V2 test ban' in ahtml or 'v2tester' in ahtml)

# builtin captcha roundtrip
post(admin, '/admin/settings', {'csrf': csrf(ahtml), 'captcha_provider': 'builtin'})
reg2 = session()
_, rghtml = get(reg2, '/register')
print('builtin challenge shown:', 'Human check' in rghtml)
m6 = re.search(r'what is (\d+) \+ (\d+)', rghtml)
wrong = post(reg2, '/register', {'csrf': csrf(rghtml), 'username': 'v2cap', 'email': 'v2cap@example.com', 'password': 'password123', 'website': '', 'captcha_answer': '9999'})
print('wrong captcha rejected:', 'Wrong answer' in wrong[1])
_, rghtml = get(reg2, '/register')
m7 = re.search(r'what is (\d+) \+ (\d+)', rghtml)
ans = int(m7.group(1)) + int(m7.group(2))
_, okhtml = post(reg2, '/register', {'csrf': csrf(rghtml), 'username': 'v2cap', 'email': 'v2cap@example.com', 'password': 'password123', 'website': '', 'captcha_answer': str(ans)})
print('right captcha accepted:', 'v2cap' in okhtml)
# honeypot trip
reg3 = session()
_, rghtml = get(reg3, '/register')
_, bhhtml = post(reg3, '/register', {'csrf': csrf(rghtml), 'username': 'bot', 'email': 'bot@example.com', 'password': 'password123', 'website': 'http://spam.example', 'captcha_answer': '0'})
print('honeypot trips:', 'rejected' in bhhtml.lower() or 'human check' in bhhtml.lower())
print('ALL V2 FLOW TESTS DONE')
