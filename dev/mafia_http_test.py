#!/usr/bin/env python3
"""End-to-end test of mafia.php against a local `php -S` server (SQLite dev database, dev-only login and time warp).
   Usage: php -S localhost:8123 -t . &   then   python3 dev/mafia_http_test.py [base_url]"""
import json, random, sys, time, urllib.request, urllib.error, urllib.parse, http.cookiejar

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8123').rstrip('/')
ok_n = fail_n = 0

def check(cond, name):
    global ok_n, fail_n
    if cond: ok_n += 1
    else:
        fail_n += 1; print('  FAIL:', name)

class Client:
    def __init__(self, n, name=None):
        self.n = n
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        if n:
            self.get('mafia.php?action=dev_login&user=%d%s' % (n, '&name=' + urllib.parse.quote(name) if name else ''))

    def raw(self, url, body=None):
        req = urllib.request.Request(BASE + '/' + url, data=json.dumps(body).encode() if body is not None else None,
                                     headers={'Content-Type': 'application/json'})
        try:
            r = self.op.open(req, timeout=20); return r.status, json.loads(r.read().decode())
        except urllib.error.HTTPError as e:
            return e.code, json.loads(e.read().decode() or '{}')

    def get(self, url): return self.raw(url)[1]
    def call(self, action, body=None): return self.raw('mafia.php?action=' + action, body or {})[1]

def warp(sec): Client(0).get('mafia.php?action=dev_warp&add=%s' % sec)
def reset_warp(): Client(0).get('mafia.php?action=dev_warp&reset=1')

def seat_row(view, seat): return view['seats'][seat]

def bot_step(c, view):
    """One human-like step using ONLY the filtered view."""
    me = view['me']; ph = view['phase']
    if not me['alive']: return
    alive = [r['seat'] for r in view['seats'] if not r['empty'] and r['alive'] and r['seat'] != me['seat']]
    def act(t, target=None, text=None):
        body = {'type': t, 'pid': view['pid']}
        if target is not None: body['target'] = target
        if text is not None: body['text'] = text
        return c.call('act', body)
    if ph == 'NIGHT' and 'night' in me and not me['night']['done']:
        role = me['role']
        if role == 'mafia':
            cands = [x for x in alive if x not in me.get('teammates', [])]
            act('mafia', random.choice(cands))
        elif role == 'doctor':
            cands = [x for x in alive + [me['seat']] if x != me.get('last_protect')]
            act('doctor', random.choice(cands))
        elif role == 'detective':
            act('detective', random.choice(alive))
    elif ph in ('VOTING', 'REVOTE') and 'vote' in view and not view['vote']['voted']:
        cands = alive if view['vote']['candidates'] is None else [x for x in alive if x in view['vote']['candidates']]
        if cands: act('vote', random.choice(cands))
    elif ph == 'DISCUSSION' and random.random() < 0.2:
        act('chat', text='Мне кажется, %s подозрительный' % view['seats'][random.choice(alive)]['name'])

def play_to_end(clients, max_steps=900, drop=None, view0=None):
    """Polls every client, lets them act, and skips game time. drop = client that stops polling."""
    views = {}
    for step in range(max_steps):
        warp(3)
        for c in clients:
            if c is drop and step > 3: continue
            r = c.call('state')
            if not r.get('ok'):
                views[c.n] = {'error': r}; continue
            v = r.get('view')
            if v is None: views[c.n] = None; continue
            views[c.n] = v
            bot_step(c, v)
        live = [v for v in views.values() if v]
        if live and all(v['phase'] == 'GAME_OVER' for v in live):
            return views, step
    return views, max_steps

random.seed(11)
reset_warp()

print('== login and access control')
guest = Client(0)
st, body = guest.raw('mafia.php?action=hub', {})
check(st == 401 and body.get('error') == 'auth' and 'аккаунт' in body.get('message', ''), 'guest is refused in Russian')
st, body = guest.raw('points.php?action=submit', {'game': 'answer-rush', 'points': 500})
check(body.get('ok') is False, 'guest cannot submit points')
users = [Client(i, 'Игрок%d' % i) for i in range(1, 9)]
A, B, C, D, E = users[:5]

print('== room: create / join / capacity / privacy')
r = A.call('create', {'size': 4, 'fill_ai': False, 'public': True})
check(r.get('ok') and len(r['code']) == 5, 'room created with a short code')
code = r['code']
check(all(ch in 'ABCDEFGHJKMNPQRSTUVWXYZ23456789' for ch in code), 'code uses unambiguous letters and digits')
check('uid' not in json.dumps(r['view']) and 'user_id' not in json.dumps(r['view']), 'view exposes no database ids')
q = B.call('quick', {'size': 4})
check(q.get('ok') and q['code'] == code, 'quick match finds the waiting public room')
check(C.call('join', {'code': code.lower()}).get('ok'), 'join by code (case-insensitive)')
check(C.call('join', {'code': code}).get('ok'), 'joining twice is harmless (same seat)')
v = C.call('state')['view']
check(sum(1 for r_ in v['seats'] if not r_['empty']) == 3, 'one seat per account')
check(D.call('join', {'code': code}).get('ok'), 'fourth player joins')
r = E.call('join', {'code': code})
check(r.get('error') in ('full', 'started'), 'fifth player cannot join a 4-room: ' + str(r.get('error')))
check(E.call('join', {'code': 'ZZZZZ'}).get('error') == 'no_room', 'stale/unknown code is refused')
r = E.call('act', {'type': 'vote', 'target': 1})
check(r.get('ok') is False and r.get('error') in ('not_member',), 'a stranger cannot act in a room')
check(E.call('state').get('view') is None, 'a stranger gets no room state')
r = B.call('start')
check(r.get('error') == 'not_host', 'only the host can start')
r = A.call('start')
check(r.get('error') == 'cannot_start', 'cannot start while players are not ready')
for c in (B, C, D): c.call('ready', {'ready': True})
r = A.call('add_ai')
check(r.get('error') == 'ai_off', 'AI seats are refused when AI is off')
r = A.call('start')
check(r.get('ok') and r['view']['phase'] == 'ROLE_REVEAL', 'game starts with the role reveal')
r = E.call('join', {'code': code})
check(r.get('error') in ('started', 'full'), 'nobody can join a running game')

print('== private information over the wire')
roles_seen = {}
for c in (A, B, C, D):
    v = c.call('state')['view']
    raw = json.dumps(v)
    roles_seen[c.n] = v['me']['role']
    others = [r_ for r_ in v['seats'] if 'role' in r_ and not r_['you']]
    check(not others, 'player %d payload holds no other player\'s role' % c.n)
    check('"result"' not in raw and '"winner"' not in raw, 'player %d payload holds no result yet' % c.n)
    check(('teammates' in v['me']) == (v['me']['role'] == 'mafia'), 'teammates only for mafia (player %d)' % c.n)
check(sorted(roles_seen.values()) == ['civilian', 'civilian', 'civilian', 'mafia'], '4 players: 1 mafia + 3 civilians')

print('== a complete 4-player match through HTTP (game time skipped)')
views, steps = play_to_end([A, B, C, D])
fin = [v for v in views.values() if v]
check(all(v['phase'] == 'GAME_OVER' for v in fin), 'match reached GAME_OVER (%d polls)' % steps)
if fin:
    res = fin[0]['result']
    check(res['winner'] in ('mafia', 'civilians') and len(res['roles']) == 4, 'result shows the winner and every role')
    check(fin[0]['result']['stats']['players'] == 4, 'result statistics present')
st = A.call('hub')
check(st['stats']['played'] == 1, 'match saved to statistics (real players only): %s' % st['stats'])
check(st['stats']['points'] > 0, 'points were awarded')
check(len(st['top']) >= 1 and 'name' in st['top'][0] and 'total' in st['top'][0], 'top players list has nickname and total')
check('email' not in json.dumps(st) and '@' not in json.dumps(st), 'no e-mail addresses in hub payload')
r = A.call('rematch')
check(r.get('ok') and r['view']['phase'] == 'LOBBY', 'rematch returns to the lobby')
for c in (B, C, D): c.call('leave')
A.call('leave')

print('== duo: 2 real players + 4 AI, one player drops out and an AI takes over')
r = A.call('create', {'mode': 'duo', 'public': True})
check(r.get('ok') and r['view']['size'] == 6 and r['view']['max_humans'] == 2, 'duo = 6-seat room for 2 real players')
dcode = r['code']
check(B.call('join', {'code': dcode}).get('ok'), 'second real player joins')
check(C.call('join', {'code': dcode}).get('error') == 'full', 'third real player refused in duo')
B.call('ready', {'ready': True})
r = A.call('start')
check(r.get('ok'), 'duo starts, missing seats filled with AI')
v = A.call('state')['view']
check(sum(1 for x in v['seats'] if x['ai']) == 4, '4 AI players seated')
names = [x['name'] for x in v['seats'] if x['ai']]
check(all(n in ['Алексей','Дмитрий','Максим','Анна','София','Илья','Мария','Никита','Ольга','Артём','Елена','Кирилл','Виктория','Павел'] for n in names), 'AI have Russian names')
role_b = B.call('state')['view']['me']['role']
views, steps = play_to_end([A, B], drop=B)
va = views.get(A.n)
check(va and va['phase'] == 'GAME_OVER', 'match finished although a real player vanished (%d polls)' % steps)
if va:
    took = [r_ for r_ in va['result']['roles'] if r_['name'] == 'Игрок2']
    check(took and took[0]['ai'] is True and took[0]['role'] == role_b, 'the AI played the same character with the same role')
r = B.call('state')
check(r.get('view') is None or r.get('error') in ('not_member', 'no_room'), 'the replaced player is no longer in the room')
st = A.call('hub')['stats']
check(st['ai_played'] >= 1 and st['played'] == 1, 'AI matches are counted separately from competitive stats: %s' % st)
A.call('leave'); B.call('leave')

print('== invitations')
A.call('hub'); B.call('hub')
r = A.call('create', {'size': 6, 'fill_ai': True, 'public': False})
hub = A.call('hub')
pub_b = [o['pub'] for o in hub['online'] if o['name'] == 'Игрок2']
check(len(pub_b) == 1 and len(pub_b[0]) == 12, 'online list shows other players by opaque id')
check(A.call('invite', {'to': 'deadbeefdead'}).get('error') == 'offline', 'unknown player cannot be invited')
check(A.call('invite', {'to': pub_b[0]}).get('ok'), 'invitation sent')
inv = B.call('hub')['invites']
check(len(inv) == 1 and inv[0]['from'] == 'Игрок1', 'invited player sees who invited them')
check(B.call('answer_invite', {'id': inv[0]['id'], 'accept': True}).get('ok'), 'invitation accepted -> joined the room')
check(B.call('answer_invite', {'id': inv[0]['id'], 'accept': True}).get('error') == 'gone', 'an invitation works once')
v = B.call('state')['view']
check(v['code'] == r['code'] and sum(1 for x in v['seats'] if not x['empty']) == 2, 'both players are in the room')
A.call('leave'); B.call('leave')

print('== quick match with nobody waiting')
r = C.call('quick', {'size': 10})
check(r.get('error') == 'no_match' and r['message'] == 'Подходящая игра пока не найдена.', 'no fake players: honest "not found" message')
r = C.call('create', {'mode': 'ai'})
check(r.get('ok') and r['view']['phase'] == 'ROLE_REVEAL' and sum(1 for x in r['view']['seats'] if x['ai']) == 5, 'play-with-AI starts at once with 5 AI')
views, steps = play_to_end([C])
check(views[C.n] and views[C.n]['phase'] == 'GAME_OVER', 'solo-vs-AI match played to the end (%d polls)' % steps)
C.call('leave')

print('== ten players')
hosts = users[:10] if len(users) >= 10 else users
big = Client(20, 'Большой')
r = A.call('create', {'size': 10, 'fill_ai': True, 'public': False})
check(r.get('ok') and r['view']['size'] == 10, '10-player room')
extra = users[1:8] + [Client(9, 'Игрок9'), Client(10, 'Игрок10'), Client(11, 'Игрок11')]
joined = 1
for c in extra:
    res = c.call('join', {'code': r['code']})
    if res.get('ok'): joined += 1
check(joined == 10, 'exactly 10 real players fit: %d' % joined)
check(big.call('join', {'code': r['code']}).get('error') == 'full', 'the 11th player is refused')
roster = [A] + [c for c in extra if c.call('state').get('view')]
for c in roster[1:]: c.call('ready', {'ready': True})
check(A.call('start').get('ok'), '10-player game starts')
views, steps = play_to_end(roster, max_steps=1500)
fin = [v for v in views.values() if v]
check(len(fin) == 10 and all(v['phase'] == 'GAME_OVER' for v in fin), '10 real players finished a match (%d polls)' % steps)

print('== points endpoint')
r = users[5].raw('points.php?action=submit', {'game': 'answer-rush', 'points': 4000})[1]
check(r.get('ok') and r['added'] == 4000, 'player can submit points from a finished game')
r = users[5].raw('points.php?action=submit', {'game': 'answer-rush', 'points': 4000})[1]
check(r.get('error') == 'rate', 'rapid duplicate submissions are refused')
r = users[5].raw('points.php?action=submit', {'game': 'mafia', 'points': 99999})[1]
check(r.get('ok') is False, 'Mafia points cannot be submitted from a browser')
r = users[5].raw('points.php?action=submit', {'game': 'history-map', 'points': 9999999})[1]
check(r.get('added') == 25000, 'points per game are capped')
top = Client(0).get('points.php?action=top')['top']
check(len(top) == 3 and top[0]['total'] >= top[1]['total'] >= top[2]['total'], 'top three sorted by total points')
print('  top:', top)

reset_warp()
print('\n%d passed, %d failed' % (ok_n, fail_n))
sys.exit(1 if fail_n else 0)
