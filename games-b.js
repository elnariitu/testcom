/* Games, part 2: Timeline Rush, History Map, History Duel, Capture the Answer */
(function () {
    'use strict';

    const LETTERS = ['A', 'B', 'C', 'D'];

    function floatText(host, text, cls) {
        const node = document.createElement('div');
        node.className = 'gp-float ' + (cls || '');
        node.textContent = text;
        host.appendChild(node);
        setTimeout(() => node.remove(), 1100);
    }

    function onKey(ctx, fn, up) {
        const handler = e => fn(e);
        document.addEventListener(up ? 'keyup' : 'keydown', handler);
        ctx.onCleanup(() => document.removeEventListener(up ? 'keyup' : 'keydown', handler));
    }

    /* ========================= 2. TIMELINE RUSH ========================= */
    Games.register({
        id: 'timeline-rush', name: 'Timeline Rush', cover: 'assets/timeline-rush.png',
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const SIZES = [4, 4, 5, 5, 6], LIMIT = 45;
            stage.innerHTML = `<div class="tl">
                <div class="tl-line"><span>PAST</span><i></i><span>PRESENT</span></div>
                <div class="tl-slots"></div>
                <div class="tl-tray"></div>
                <div class="tl-status"></div>
                <button class="gp-btn primary tl-check" disabled>CHECK ORDER</button></div>`;
            const slotsBox = stage.querySelector('.tl-slots');
            const tray = stage.querySelector('.tl-tray');
            const status = stage.querySelector('.tl-status');
            const checkBtn = stage.querySelector('.tl-check');

            let round = 0, cards = [], placed = [], selected = null, closed = true, timerHandle = null, startedAt = 0, plans = [], resolved = {}, drag = null;

            function pickEvents(n) {
                const pool = GameData.timelineEvents;
                for (let tries = 0; tries < 60; tries++) {
                    const set = GameData.shuffle(pool).slice(0, n).sort((a, b) => a.y - b.y);
                    if (set.every((e, i) => i === 0 || e.y - set[i - 1].y >= 8)) return set;
                }
                return GameData.shuffle(pool).slice(0, n).sort((a, b) => a.y - b.y);
            }

            function cardHTML(c, extra) {
                return `<div class="tl-card ${extra || ''}" data-c="${c.id}"><span>${esc(c.t)}</span><small class="yr">${c.shown ? c.y : ''}</small></div>`;
            }

            function render() {
                slotsBox.innerHTML = placed.map((id, i) => `<div class="tl-slot ${id != null && cards[id].state ? cards[id].state : ''}" data-i="${i}">${id != null ? cardHTML(cards[id], selected === id ? 'sel' : '') : `<em>${i + 1}</em>`}</div>`).join('');
                tray.innerHTML = cards.filter(c => placed.indexOf(c.id) === -1).map(c => cardHTML(c, selected === c.id ? 'sel' : '')).join('');
                checkBtn.disabled = closed || placed.some(id => id == null);
            }

            function renderStatus() {
                if (players.length < 2) { status.innerHTML = ''; return; }
                status.innerHTML = players.map(p => {
                    const r = resolved[p.id];
                    return `<span class="st ${r ? 'done' : ''}"><i style="background:${p.color}"></i>${esc(p.name)} ${r ? (r.perfect ? '&#10003;' : '&#10003;~') : 'arranging...'}</span>`;
                }).join('');
            }

            function nextRound() {
                if (round >= SIZES.length) return ctx.finish();
                const n = SIZES[round++];
                ctx.setRound(round, SIZES.length);
                const events = pickEvents(n);
                cards = GameData.shuffle(events.map((e, i) => ({ id: i, y: e.y, t: e.t, rank: i })));
                cards.forEach((c, i) => { c.id = i; });
                const byYear = cards.slice().sort((a, b) => a.y - b.y);
                byYear.forEach((c, i) => { c.rank = i; });
                placed = new Array(n).fill(null);
                selected = null; closed = false; resolved = {}; startedAt = ctx.clock.t;
                checkBtn.textContent = 'CHECK ORDER';
                render(); renderStatus();
                timerHandle = ctx.timer(LIMIT, () => finishRound(true));
                plans = players.filter(p => p.bot).map(bot => {
                    const at = 9000 + Math.random() * 22000 * (6 / (bot.bot.spd + 1));
                    const perfect = Math.random() < Math.pow(bot.bot.acc, n / 4);
                    const plan = { bot, at: Math.min(at, (LIMIT - 4) * 1000), perfect, n };
                    ctx.after(plan.at, () => botDone(plan));
                    return plan;
                });
            }

            function grade(pid, perfect, correctCount, n, ms) {
                const speed = Math.round(500 * Math.max(0, 1 - ms / (LIMIT * 1000)));
                if (perfect) return ctx.award(pid, true, 1000 + speed, ms);
                ctx.addScore(pid, Math.round(700 * correctCount / n));
                ctx.award(pid, false, 0, ms);
                return Math.round(700 * correctCount / n);
            }

            function botDone(plan) {
                if (resolved[plan.bot.id]) return;
                resolved[plan.bot.id] = { perfect: plan.perfect };
                grade(plan.bot.id, plan.perfect, plan.perfect ? plan.n : Math.max(1, plan.n - 2), plan.n, plan.at);
                renderStatus();
            }

            function finishRound(timeout) {
                if (closed && !timeout) return;
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                // fill any empty slots in tray order when time ran out
                cards.filter(c => placed.indexOf(c.id) === -1).forEach(c => { placed[placed.indexOf(null)] = c.id; });
                const n = placed.length;
                let good = 0;
                placed.forEach((id, i) => {
                    const ok = cards[id].rank === i;
                    if (ok) good++;
                    cards[id].state = ok ? 'right' : 'wrong';
                    cards[id].shown = true;
                });
                const perfect = good === n;
                const ms = ctx.clock.t - startedAt;
                const pts = grade(me.id, perfect, good, n, ms);
                ctx.emit('done', { r: round, perfect, good, n, ms });
                resolved[me.id] = { perfect };
                plans.forEach(botDone);
                ctx.sfx(perfect ? 'correct' : 'wrong');
                render(); renderStatus();
                floatText(stage.querySelector('.tl'), (perfect ? 'PERFECT +' : '+') + ctx.fmt(pts), perfect ? 'good' : 'bad');
                checkBtn.textContent = perfect ? 'PERFECT!' : good + ' / ' + n + ' RIGHT';
                ctx.after(2400, nextRound);
            }

            function place(id, slot) {
                const from = placed.indexOf(id);
                if (from !== -1) placed[from] = null;
                placed[slot] = id;
                selected = null;
            }

            stage.addEventListener('pointerdown', e => {
                const c = e.target.closest('.tl-card');
                if (!c || closed) return;
                drag = { id: Number(c.dataset.c), x: e.clientX, y: e.clientY, moved: false, ghost: null };
            });
            const onMove = e => {
                if (!drag) return;
                if (!drag.moved && Math.hypot(e.clientX - drag.x, e.clientY - drag.y) > 6) {
                    drag.moved = true;
                    drag.ghost = document.createElement('div');
                    drag.ghost.className = 'tl-card ghost';
                    drag.ghost.innerHTML = `<span>${esc(cards[drag.id].t)}</span>`;
                    document.body.appendChild(drag.ghost);
                }
                if (drag.ghost) { drag.ghost.style.left = e.clientX + 'px'; drag.ghost.style.top = e.clientY + 'px'; }
            };
            const onUp = e => {
                if (!drag) return;
                const d = drag;
                drag = null;
                if (d.ghost) d.ghost.remove();
                if (closed) return;
                const target = document.elementFromPoint(e.clientX, e.clientY);
                const slot = target && target.closest('.tl-slot');
                if (d.moved) {
                    if (slot && stage.contains(slot)) place(d.id, Number(slot.dataset.i));
                    else if (target && target.closest('.tl-tray')) { const at = placed.indexOf(d.id); if (at !== -1) placed[at] = null; }
                } else if (placed.indexOf(d.id) !== -1) {
                    placed[placed.indexOf(d.id)] = null;
                } else {
                    selected = selected === d.id ? null : d.id;
                }
                render();
            };
            stage.addEventListener('click', e => {
                if (closed) return;
                const slot = e.target.closest('.tl-slot');
                if (slot && selected !== null && !slot.querySelector('.tl-card')) { place(selected, Number(slot.dataset.i)); render(); }
            });
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
            ctx.onCleanup(() => { document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp); document.querySelectorAll('.tl-card.ghost').forEach(n => n.remove()); });
            checkBtn.addEventListener('click', () => { if (!checkBtn.disabled) finishRound(false); });
            ctx.onRemote('done', (p, b) => {
                if (closed && resolved[p.id]) return;
                if (resolved[p.id]) return;
                resolved[p.id] = { perfect: !!b.perfect };
                grade(p.id, !!b.perfect, b.good, b.n, b.ms);
                renderStatus();
            });
            nextRound();
        }
    });

    /* ========================= 3. HISTORY MAP ========================= */
    Games.register({
        id: 'history-map', name: 'History Map', cover: 'assets/history-map.png',
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const ROUNDS = 8, LIMIT = 15, K = 32, COS = Math.cos(48 * Math.PI / 180);
            const proj = (lon, lat) => [(lon - 46) * COS * K, (56 - lat) * K];
            const unproj = (x, y) => [x / (COS * K) + 46, 56 - y / K];
            const km = (a, b) => {
                const R = 6371, rad = Math.PI / 180;
                const dLat = (b[1] - a[1]) * rad, dLon = (b[0] - a[0]) * rad;
                const h = Math.sin(dLat / 2) ** 2 + Math.cos(a[1] * rad) * Math.cos(b[1] * rad) * Math.sin(dLon / 2) ** 2;
                return 2 * R * Math.asin(Math.sqrt(h));
            };
            const outline = GameData.mapOutline.map(([lo, la], i) => (i ? 'L' : 'M') + proj(lo, la).map(v => v.toFixed(1)).join(' ')).join(' ') + ' Z';
            const targets = GameData.shuffle(GameData.mapLocations).slice(0, ROUNDS);

            stage.innerHTML = `<div class="hm"><div class="hm-q"></div>
                <svg class="hm-svg" viewBox="0 0 900 500" preserveAspectRatio="xMidYMid meet">
                    <defs><linearGradient id="hmg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3f8f54"/><stop offset="1" stop-color="#b9a34a"/></linearGradient></defs>
                    <path class="hm-land" d="${outline}"/>
                    <text class="hm-sea" x="14" y="330">CASPIAN SEA</text><text class="hm-sea" x="245" y="376">ARAL SEA</text>
                    <text class="hm-nb" x="440" y="26">RUSSIA</text><text class="hm-nb" x="800" y="470">CHINA</text><text class="hm-nb" x="330" y="488">UZBEKISTAN</text>
                    <circle class="hm-ring" r="16" cx="-50" cy="-50"/><g class="hm-layer"></g></svg>
                <div class="hm-info"></div></div>`;
            const svg = stage.querySelector('.hm-svg');
            const layer = stage.querySelector('.hm-layer');
            const ring = stage.querySelector('.hm-ring');
            const qBox = stage.querySelector('.hm-q');
            const info = stage.querySelector('.hm-info');

            let round = 0, target = null, clicks = {}, closed = true, timerHandle = null, startedAt = 0;

            function pin(x, y, color, label, cls) {
                return `<g class="hm-pin ${cls || ''}" transform="translate(${x.toFixed(1)} ${y.toFixed(1)})"><path d="M0 0C-12-16-16-24-16-32a16 16 0 0132 0C16-24 12-16 0 0z" fill="${color}"/><circle cy="-32" r="6" fill="#fff"/>${label ? `<text y="14" text-anchor="middle">${esc(label)}</text>` : ''}</g>`;
            }

            function nextRound() {
                if (round >= ROUNDS) return ctx.finish();
                target = targets[round++];
                ctx.setRound(round, ROUNDS);
                clicks = {}; closed = false; startedAt = ctx.clock.t;
                layer.innerHTML = '';
                info.textContent = '';
                info.classList.remove('show');
                qBox.textContent = 'Find: ' + target.q;
                timerHandle = ctx.timer(LIMIT, () => closeRound());
                const spot = proj(target.lon, target.lat);
                players.filter(p => p.bot).forEach(bot => {
                    const delay = ctx.botDelay(bot, LIMIT);
                    ctx.after(delay, () => {
                        const spread = (1.15 - bot.bot.acc) * 620;
                        const ang = Math.random() * Math.PI * 2, dist = Math.random() * spread;
                        const pt = [spot[0] + Math.cos(ang) * dist / 3.3, spot[1] + Math.sin(ang) * dist / 3.3];
                        register(bot, unproj(pt[0], pt[1]), delay);
                    });
                });
            }

            function register(p, lonlat, ms) {
                if (closed || clicks[p.id]) return;
                clicks[p.id] = { lonlat, ms };
                if (p.isYou) ctx.emit('click', { r: round, lon: lonlat[0], lat: lonlat[1], ms });
                if (p.isYou) {
                    const [x, y] = proj(lonlat[0], lonlat[1]);
                    layer.insertAdjacentHTML('beforeend', pin(x, y, p.color, 'You', 'mine'));
                    ctx.sfx('tick');
                }
                if (players.every(x => clicks[x.id])) closeRound();
            }

            function closeRound() {
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                const spot = proj(target.lon, target.lat);
                players.forEach(p => {
                    const c = clicks[p.id];
                    if (!c) { ctx.award(p.id, false, 0, LIMIT * 1000); return; }
                    const d = km(c.lonlat, [target.lon, target.lat]);
                    const base = Math.max(0, Math.round(1000 - d * 1.25));
                    const ok = d <= 250;
                    if (ok) ctx.award(p.id, true, base + Math.round(200 * Math.max(0, 1 - c.ms / (LIMIT * 1000))), c.ms);
                    else { ctx.addScore(p.id, base); ctx.award(p.id, false, 0, c.ms); }
                    c.d = d; c.ok = ok;
                    const [x, y] = proj(c.lonlat[0], c.lonlat[1]);
                    if (!p.isYou) layer.insertAdjacentHTML('beforeend', pin(x, y, p.color, players.length > 1 ? p.name : '', 'bot'));
                    layer.insertAdjacentHTML('afterbegin', `<line class="hm-link ${ok ? 'ok' : 'bad'}" x1="${x.toFixed(1)}" y1="${y.toFixed(1)}" x2="${spot[0].toFixed(1)}" y2="${spot[1].toFixed(1)}"/>`);
                });
                layer.insertAdjacentHTML('beforeend', `<g class="hm-target" transform="translate(${spot[0].toFixed(1)} ${spot[1].toFixed(1)})"><circle r="22"/><circle r="8"/><text y="-30" text-anchor="middle">${esc(target.name)}</text></g>`);
                const mine = clicks[me.id];
                const ok = mine && mine.ok;
                ctx.sfx(ok ? 'correct' : 'wrong');
                info.textContent = target.info + (mine ? '  (' + Math.round(mine.d) + ' km off)' : '  (no answer)');
                info.classList.add('show');
                info.classList.toggle('good', !!ok);
                ctx.after(2600, nextRound);
            }

            svg.addEventListener('click', e => {
                if (closed || clicks[me.id]) return;
                const pt = svg.createSVGPoint();
                pt.x = e.clientX; pt.y = e.clientY;
                const p = pt.matrixTransform(svg.getScreenCTM().inverse());
                register(me, unproj(p.x, p.y), ctx.clock.t - startedAt);
            });
            ctx.onRemote('click', (p, b) => register(p, [b.lon, b.lat], b.ms));
            svg.addEventListener('mousemove', e => {
                const pt = svg.createSVGPoint();
                pt.x = e.clientX; pt.y = e.clientY;
                const p = pt.matrixTransform(svg.getScreenCTM().inverse());
                ring.setAttribute('cx', p.x.toFixed(1));
                ring.setAttribute('cy', p.y.toFixed(1));
            });
            nextRound();
        }
    });

    /* ========================= 6. HISTORY DUEL ========================= */
    Games.register({
        id: 'history-duel', name: 'History Duel', cover: 'assets/history-duel.png', needsRival: true,
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const ROUNDS = 10, LIMIT = 12;
            const teamOf = p => (players.indexOf(p) < Math.ceil(players.length / 2) ? 'blue' : 'red');
            const team = { blue: players.filter(p => teamOf(p) === 'blue'), red: players.filter(p => teamOf(p) === 'red') };
            const hp = {}, power = {}, boosted = {};
            players.forEach(p => { hp[p.id] = 100; power[p.id] = 0; boosted[p.id] = false; });
            const questions = ctx.questions(ROUNDS);

            const fighter = p => `<div class="du-fighter" data-id="${p.id}"><div class="du-avatar ${teamOf(p)}"><svg viewBox="0 0 64 72"><path d="M32 2L60 12v22c0 18-12 30-28 36C16 64 4 52 4 34V12z"/><text x="32" y="46" text-anchor="middle">${esc(p.name[0])}</text></svg></div>
                <div class="du-name">${esc(p.name)}${p.bot ? ' <em>AI</em>' : ''}</div><div class="du-hp"><i></i><b>100</b></div><div class="du-pow"><i></i></div></div>`;
            stage.innerHTML = `<div class="du"><div class="du-team blue">${team.blue.map(fighter).join('')}</div>
                <div class="du-center"><div class="du-q"></div><div class="du-answers"></div>
                    <button class="gp-btn du-boost" disabled>BOOST</button></div>
                <div class="du-team red">${team.red.map(fighter).join('')}</div></div>`;
            const qBox = stage.querySelector('.du-q');
            const ansBox = stage.querySelector('.du-answers');
            const boostBtn = stage.querySelector('.du-boost');

            let round = 0, q = null, answered = {}, closed = true, timerHandle = null, startedAt = 0, over = false;

            const node = p => stage.querySelector(`.du-fighter[data-id="${p.id}"]`);
            function paint() {
                players.forEach(p => {
                    const n = node(p);
                    n.querySelector('.du-hp i').style.width = hp[p.id] + '%';
                    n.querySelector('.du-hp b').textContent = Math.max(0, Math.round(hp[p.id]));
                    n.querySelector('.du-pow i').style.width = power[p.id] + '%';
                    n.classList.toggle('down', hp[p.id] <= 0);
                    n.classList.toggle('charged', power[p.id] >= 100);
                    n.classList.toggle('boosted', boosted[p.id]);
                });
                boostBtn.disabled = closed || power[me.id] < 100 || boosted[me.id] || answered[me.id] !== undefined;
                boostBtn.classList.toggle('ready', power[me.id] >= 100 && !boosted[me.id]);
            }
            const alive = list => list.filter(p => hp[p.id] > 0);

            function nextRound() {
                if (over || round >= ROUNDS || !alive(team.blue).length || !alive(team.red).length) return endMatch();
                q = questions[round++];
                ctx.setRound(round, ROUNDS);
                answered = {}; closed = false; startedAt = ctx.clock.t;
                qBox.textContent = q.question;
                ansBox.classList.remove('locked');
                ansBox.innerHTML = q.answers.map((a, i) => `<button class="du-ans" data-i="${i}"><b>${LETTERS[i]}</b><span>${esc(a)}</span></button>`).join('');
                paint();
                timerHandle = ctx.timer(LIMIT, () => resolveRound());
                players.filter(p => p.bot && hp[p.id] > 0).forEach(bot => {
                    if (power[bot.id] >= 100) boosted[bot.id] = true;
                    const delay = ctx.botDelay(bot, LIMIT);
                    const right = ctx.botCorrect(bot, q.difficulty);
                    ctx.after(delay, () => choose(bot, right ? q.correct : (q.correct + 1 + Math.floor(Math.random() * 3)) % 4, delay));
                });
            }

            function choose(p, index, ms) {
                if (closed || hp[p.id] <= 0 || answered[p.id] !== undefined) return;
                answered[p.id] = { index, ms };
                if (p.isYou) ctx.emit('ans', { r: round, i: index, ms });
                if (p.isYou) {
                    const btn = ansBox.querySelector(`[data-i="${index}"]`);
                    if (btn) btn.classList.add('picked');
                    ansBox.classList.add('locked');
                    ctx.sfx('tick');
                }
                paint();
                const need = players.filter(x => hp[x.id] > 0);
                if (need.every(x => answered[x.id] !== undefined)) resolveRound();
            }

            function resolveRound() {
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                const right = ansBox.querySelector(`[data-i="${q.correct}"]`);
                if (right) right.classList.add('right');
                const hits = players.filter(p => answered[p.id] && answered[p.id].index === q.correct).sort((a, b) => answered[a.id].ms - answered[b.id].ms);
                players.filter(p => hp[p.id] > 0 && !(answered[p.id] && answered[p.id].index === q.correct)).forEach(p => {
                    power[p.id] = Math.max(0, power[p.id] - 20);
                    ctx.award(p.id, false, 0, answered[p.id] ? answered[p.id].ms : LIMIT * 1000);
                    boosted[p.id] = false;
                });
                let delay = 0;
                hits.forEach(p => {
                    ctx.after(delay, () => {
                        const foes = alive(teamOf(p) === 'blue' ? team.red : team.blue).sort((a, b) => hp[b.id] - hp[a.id]);
                        if (!foes.length) return;
                        const foe = foes[0];
                        const ms = answered[p.id].ms;
                        let dmg = 10 + Math.round(5 * Math.max(0, 1 - ms / (LIMIT * 1000)));
                        if (boosted[p.id]) { dmg *= 2; boosted[p.id] = false; power[p.id] = 0; }
                        else power[p.id] = Math.min(100, power[p.id] + 20 + (ctx.stats[p.id].combo >= 2 ? 10 : 0));
                        hp[foe.id] = Math.max(0, hp[foe.id] - dmg);
                        ctx.award(p.id, true, dmg * 20, ms);
                        const a = node(p), t = node(foe);
                        a.classList.add('attack');
                        t.classList.add('hit');
                        setTimeout(() => { a.classList.remove('attack'); t.classList.remove('hit'); }, 500);
                        floatText(t, '-' + dmg, 'bad');
                        if (p.isYou) ctx.sfx('correct');
                        paint();
                    });
                    delay += 450;
                });
                if (!hits.length) ctx.sfx('wrong');
                ctx.after(Math.max(1400, delay + 900), nextRound);
                paint();
            }

            function endMatch() {
                if (over) return;
                over = true;
                const sum = list => list.reduce((s, p) => s + Math.max(0, hp[p.id]), 0);
                const blue = sum(team.blue), red = sum(team.red);
                const winners = blue === red ? [] : (blue > red ? team.blue : team.red);
                winners.forEach(p => ctx.addScore(p.id, 500));
                qBox.textContent = blue === red ? 'DRAW!' : (blue > red ? 'BLUE TEAM WINS!' : 'RED TEAM WINS!');
                ansBox.innerHTML = '';
                if (winners.includes(me)) { ctx.confetti(80); ctx.sfx('win'); }
                ctx.finish();
            }

            ansBox.addEventListener('click', e => {
                const b = e.target.closest('.du-ans');
                if (b && !closed) choose(me, Number(b.dataset.i), ctx.clock.t - startedAt);
            });
            boostBtn.addEventListener('click', () => {
                if (boostBtn.disabled) return;
                boosted[me.id] = true;
                ctx.emit('boost', { r: round });
                ctx.sfx('boost');
                paint();
            });
            onKey(ctx, e => { const i = 'abcd'.indexOf(e.key.toLowerCase()); if (i >= 0 && !ctx.paused && !closed) choose(me, i, ctx.clock.t - startedAt); });
            ctx.onRemote('ans', (p, b) => choose(p, b.i, b.ms));
            ctx.onRemote('boost', p => { if (!closed && power[p.id] >= 100) { boosted[p.id] = true; paint(); } });
            ctx.onReplaced(p => { const n = node(p); if (n) n.querySelector('.du-name').innerHTML = `${esc(p.name)} <em>AI</em>`; });
            nextRound();
        }
    });

    /* ========================= 7. CAPTURE THE ANSWER ========================= */
    Games.register({
        id: 'capture-the-answer', name: 'Capture the Answer', cover: 'assets/capture-the-answer.png',
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const ROUNDS = 6, LIMIT = 8;
            const ZONE_COLORS = ['#ef4444', '#3b82f6', '#22c55e', '#f5b301'];
            const questions = ctx.questions(ROUNDS);
            stage.innerHTML = `<div class="ca"><div class="ca-q"></div>
                <div class="ca-arena">${[0, 1, 2, 3].map(i => `<div class="ca-zone z${i}" data-z="${i}" style="--zc:${ZONE_COLORS[i]}"><b>${LETTERS[i]}</b><span></span></div>`).join('')}
                    <div class="ca-orb hidden"></div><div class="ca-lock hidden"></div>
                    ${players.map(p => `<div class="ca-char" data-id="${p.id}" style="--pc:${p.color}"><i></i><em>${esc(p.name)}</em></div>`).join('')}
                    <div class="ca-joy"><div class="knob"></div></div></div></div>`;
            const arena = stage.querySelector('.ca-arena');
            const qBox = stage.querySelector('.ca-q');
            const lockEl = stage.querySelector('.ca-lock');
            const orbEl = stage.querySelector('.ca-orb');
            const joy = stage.querySelector('.ca-joy');
            const knob = joy.querySelector('.knob');

            const pos = {}, target = {}, frozenUntil = {}, boostUntil = {}, delayUntil = {}, remoteAt = {};
            let lastSent = 0, currentRound = 0;
            let round = 0, q = null, W = 1, H = 1, closed = true, timerHandle = null, keys = {}, joyVec = [0, 0], orb = null, lastFrame = 0, raf = 0, locking = false;

            const zoneEl = i => arena.querySelector(`.z${i}`);
            const charEl = p => arena.querySelector(`.ca-char[data-id="${p.id}"]`);
            function zoneRect(i) { const r = zoneEl(i).getBoundingClientRect(), a = arena.getBoundingClientRect(); return { x: r.left - a.left, y: r.top - a.top, w: r.width, h: r.height }; }
            function zoneAt(x, y) { for (let i = 0; i < 4; i++) { const r = zoneRect(i); if (x >= r.x && x <= r.x + r.w && y >= r.y && y <= r.y + r.h) return i; } return -1; }

            function measure() { const r = arena.getBoundingClientRect(); W = r.width; H = r.height; }

            function nextRound() {
                if (round >= ROUNDS) return ctx.finish();
                q = questions[round++];
                currentRound = round;
                ctx.setRound(round, ROUNDS);
                measure();
                qBox.textContent = q.question;
                q.answers.forEach((a, i) => { const z = zoneEl(i); z.querySelector('span').textContent = a; z.classList.remove('locked', 'right', 'wrong'); });
                lockEl.classList.add('hidden'); locking = false; closed = false;
                players.forEach((p, i) => {
                    delete remoteAt[p.id];
                    pos[p.id] = { x: W / 2 + (i - (players.length - 1) / 2) * 46, y: H / 2 };
                    frozenUntil[p.id] = 0; boostUntil[p.id] = 0;
                    const good = p.bot && ctx.botCorrect(p, q.difficulty);
                    const zoneIndex = good ? q.correct : Math.floor(Math.random() * 4);
                    target[p.id] = zoneIndex;
                    delayUntil[p.id] = ctx.clock.t + 700 + Math.random() * 2200;
                });
                spawnOrb();
                timerHandle = ctx.timer(LIMIT, () => lockRound(), left => {
                    if (left <= 3 && left > 0 && !locking) { locking = true; }
                    if (left <= 3 && left > 0) { lockEl.classList.remove('hidden'); lockEl.textContent = Math.ceil(left); }
                });
            }

            function spawnOrb() {
                orb = { type: ctx.rng() < 0.5 ? 'boost' : 'freeze', x: W * (0.3 + ctx.rng() * 0.4), y: H * (0.3 + ctx.rng() * 0.4) };
                orbEl.className = 'ca-orb ' + orb.type;
                orbEl.textContent = orb.type === 'boost' ? '⚡' : '❄';
                orbEl.style.left = orb.x + 'px';
                orbEl.style.top = orb.y + 'px';
            }

            function lockRound() {
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                lockEl.classList.remove('hidden');
                lockEl.textContent = 'LOCK!';
                ctx.sfx('go');
                for (let i = 0; i < 4; i++) zoneEl(i).classList.add('locked');
                zoneEl(q.correct).classList.add('right');
                let wins = 0;
                players.forEach(p => {
                    const inside = zoneAt(pos[p.id].x, pos[p.id].y) === q.correct;
                    ctx.award(p.id, inside, 1000, LIMIT * 1000);
                    if (inside && p.isYou) wins++;
                });
                ctx.sfx(wins ? 'correct' : 'wrong');
                floatText(arena, wins ? '+1000' : 'MISSED', wins ? 'good' : 'bad');
                ctx.after(2200, () => { lockEl.classList.add('hidden'); nextRound(); });
            }

            function step(now) {
                raf = requestAnimationFrame(step);
                const dt = Math.min(0.05, (now - lastFrame) / 1000 || 0);
                lastFrame = now;
                if (ctx.paused || closed) return;
                const speedBase = W * 0.34;
                players.forEach(p => {
                    const cur = pos[p.id];
                    if (!cur) return;
                    if (ctx.clock.t < frozenUntil[p.id]) { charEl(p).classList.add('frozen'); return; }
                    charEl(p).classList.remove('frozen');
                    let vx = 0, vy = 0;
                    if (p.remote) {
                        const goal = remoteAt[p.id];
                        if (goal) {
                            const k = Math.min(1, dt * 9);
                            cur.x += (goal.x - cur.x) * k;
                            cur.y += (goal.y - cur.y) * k;
                            const elr = charEl(p);
                            elr.style.transform = `translate(${cur.x - 18}px, ${cur.y - 18}px)`;
                            const zr = zoneAt(cur.x, cur.y);
                            if (zr !== -1 && !elr.dataset.z) { elr.classList.remove('hop'); void elr.offsetWidth; elr.classList.add('hop'); }
                            elr.dataset.z = zr === -1 ? '' : String(zr);
                        }
                        return;
                    }
                    if (p.isYou) {
                        vx = (keys.arrowright || keys.d ? 1 : 0) - (keys.arrowleft || keys.a ? 1 : 0) + joyVec[0];
                        vy = (keys.arrowdown || keys.s ? 1 : 0) - (keys.arrowup || keys.w ? 1 : 0) + joyVec[1];
                    } else if (ctx.clock.t >= delayUntil[p.id]) {
                        const r = zoneRect(target[p.id]);
                        const gx = r.x + r.w / 2 - cur.x, gy = r.y + r.h / 2 - cur.y;
                        const d = Math.hypot(gx, gy);
                        if (d > 8) { vx = gx / d; vy = gy / d; }
                    }
                    const len = Math.hypot(vx, vy);
                    if (len > 1) { vx /= len; vy /= len; }
                    const speed = speedBase * (ctx.clock.t < boostUntil[p.id] ? 1.7 : 1);
                    const before = zoneAt(cur.x, cur.y);
                    cur.x = ctx.clamp(cur.x + vx * speed * dt, 18, W - 18);
                    cur.y = ctx.clamp(cur.y + vy * speed * dt, 18, H - 18);
                    const el = charEl(p);
                    el.style.transform = `translate(${cur.x - 18}px, ${cur.y - 18}px)`;
                    el.classList.toggle('run', len > 0.05);
                    if (p.isYou && ctx.online && ctx.clock.t - lastSent > 150) {
                        lastSent = ctx.clock.t;
                        ctx.emit('pos', { r: currentRound, x: +(cur.x / W).toFixed(4), y: +(cur.y / H).toFixed(4) });
                    }
                    const after = zoneAt(cur.x, cur.y);
                    if (after !== before && after !== -1) { el.classList.remove('hop'); void el.offsetWidth; el.classList.add('hop'); }
                    if (orb && Math.hypot(cur.x - orb.x, cur.y - orb.y) < 30) {
                        if (orb.type === 'boost') boostUntil[p.id] = ctx.clock.t + 3000;
                        else players.forEach(o => { if (o !== p) frozenUntil[o.id] = ctx.clock.t + 1200; });
                        if (p.isYou) ctx.sfx('boost');
                        orb = null;
                        orbEl.classList.add('hidden');
                    }
                });
            }

            onKey(ctx, e => { keys[e.key.toLowerCase()] = true; if (e.key.startsWith('Arrow')) e.preventDefault(); });
            onKey(ctx, e => { keys[e.key.toLowerCase()] = false; }, true);
            const joyMove = e => {
                const r = joy.getBoundingClientRect();
                const dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
                const max = r.width / 2;
                const len = Math.min(max, Math.hypot(dx, dy)) || 0;
                const ang = Math.atan2(dy, dx);
                joyVec = [Math.cos(ang) * len / max, Math.sin(ang) * len / max];
                knob.style.transform = `translate(${Math.cos(ang) * len}px, ${Math.sin(ang) * len}px)`;
            };
            let joyOn = false;
            joy.addEventListener('pointerdown', e => { joyOn = true; joy.setPointerCapture(e.pointerId); joyMove(e); });
            joy.addEventListener('pointermove', e => { if (joyOn) joyMove(e); });
            const joyEnd = () => { joyOn = false; joyVec = [0, 0]; knob.style.transform = ''; };
            joy.addEventListener('pointerup', joyEnd);
            joy.addEventListener('pointercancel', joyEnd);
            window.addEventListener('resize', measure);
            ctx.onRemote('pos', (p, b) => { if (!closed) remoteAt[p.id] = { x: b.x * W, y: b.y * H }; });
            ctx.onReplaced(p => { const c = charEl(p); if (c) c.querySelector('em').textContent = p.name; delete remoteAt[p.id]; target[p.id] = Math.floor(Math.random() * 4); delayUntil[p.id] = ctx.clock.t + 900; });
            ctx.onCleanup(() => { cancelAnimationFrame(raf); window.removeEventListener('resize', measure); });
            raf = requestAnimationFrame(step);
            nextRound();
        }
    });
})();
