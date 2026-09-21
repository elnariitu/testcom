/* Games, part 1: Answer Rush, True or Trap, Who Am I?, History Millionaire */
(function () {
    'use strict';

    const LETTERS = ['A', 'B', 'C', 'D'];
    const BALL_COLORS = ['#3b82f6', '#ef4444', '#22c55e', '#f5b301'];

    function onKey(ctx, fn) {
        const handler = e => { if (!e.repeat && !ctx.paused) fn(e); };
        document.addEventListener('keydown', handler);
        ctx.onCleanup(() => document.removeEventListener('keydown', handler));
    }

    function floatText(host, text, cls) {
        const node = document.createElement('div');
        node.className = 'gp-float ' + (cls || '');
        node.textContent = text;
        host.appendChild(node);
        setTimeout(() => node.remove(), 1100);
    }

    /* ========================= 1. ANSWER RUSH ========================= */
    Games.register({
        id: 'answer-rush', name: 'Answer Rush', cover: 'assets/answer-rush.png', needsRival: true,
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const ROUNDS = 10, LIMIT = 10;
            const questions = ctx.questions(ROUNDS);
            const progress = {};
            players.forEach(p => { progress[p.id] = 0; });
            stage.innerHTML = `<div class="ar">
                <div class="ar-q"></div>
                <div class="ar-track">${players.map(p => `<div class="ar-lane" data-id="${p.id}">
                    <div class="ar-rail"><div class="ar-flag">&#127937;</div><div class="ar-ball" style="--c:${p.color}"><i></i></div></div>
                    <span class="ar-tag">${esc(p.name)}</span></div>`).join('')}</div>
                <div class="ar-meters"><div class="ar-speed"><b>120</b> km/h</div>
                    <div class="ar-combo"><span>COMBO</span><div class="ar-cbar"><i></i></div></div></div>
                <div class="ar-answers"></div></div>`;
            const qBox = stage.querySelector('.ar-q');
            const answersBox = stage.querySelector('.ar-answers');
            const speedEl = stage.querySelector('.ar-speed b');
            const comboBar = stage.querySelector('.ar-cbar i');

            let round = 0, q = null, answered = {}, closed = true, timerHandle = null, startedAt = 0, winner = false;

            function ball(p) { return stage.querySelector(`.ar-lane[data-id="${p.id}"] .ar-ball`); }
            function moveBall(p) {
                const pct = Math.min(100, progress[p.id]);
                ball(p).style.left = (pct * 0.9) + '%';
            }
            function meters() {
                const s = ctx.stats[me.id];
                speedEl.textContent = 120 + s.combo * 35;
                comboBar.style.width = Math.min(100, s.combo * 20) + '%';
            }

            function nextRound() {
                if (winner || round >= ROUNDS) return ctx.finish();
                q = questions[round++];
                ctx.setRound(round, ROUNDS);
                answered = {};
                closed = false;
                startedAt = ctx.clock.t;
                qBox.textContent = q.question;
                answersBox.classList.remove('locked');
                answersBox.innerHTML = q.answers.map((a, i) => `<button class="ar-ans" data-i="${i}" style="--c:${BALL_COLORS[i]}">
                    <span class="ball">${LETTERS[i]}</span><span class="txt">${esc(a)}</span></button>`).join('');
                timerHandle = ctx.timer(LIMIT, () => closeRound());
                players.filter(p => p.bot).forEach(bot => {
                    const delay = ctx.botDelay(bot, LIMIT);
                    const right = ctx.botCorrect(bot, q.difficulty);
                    ctx.after(delay, () => submit(bot, right ? q.correct : (q.correct + 1 + Math.floor(Math.random() * 3)) % 4, delay));
                });
            }

            function submit(p, index, ms) {
                if (closed || answered[p.id] !== undefined) return;
                answered[p.id] = index;
                if (p.isYou) ctx.emit('ans', { r: round, i: index, ms });
                const ok = index === q.correct;
                const speed = Math.round(500 * Math.max(0, 1 - ms / (LIMIT * 1000)));
                const pts = ctx.award(p.id, ok, 500 + speed, ms);
                const el = ball(p);
                el.classList.remove('boost', 'slow');
                void el.offsetWidth;
                if (ok) {
                    progress[p.id] += 8 + 4 * speed / 500;
                    el.classList.add('boost');
                    floatText(el.parentElement, '+' + ctx.fmt(pts), 'good');
                } else {
                    progress[p.id] = Math.max(0, progress[p.id] - 1);
                    el.classList.add('slow');
                }
                moveBall(p);
                if (p.isYou) {
                    const btn = answersBox.querySelector(`[data-i="${index}"]`);
                    if (btn) btn.classList.add(ok ? 'right' : 'wrong');
                    ctx.sfx(ok ? 'correct' : 'wrong');
                    answersBox.classList.add('locked');
                }
                meters();
                if (players.every(x => answered[x.id] !== undefined)) closeRound();
            }

            function closeRound() {
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                players.forEach(p => {
                    if (answered[p.id] === undefined) { ctx.award(p.id, false, 0, LIMIT * 1000); ball(p).classList.add('slow'); }
                });
                answersBox.classList.add('locked');
                const right = answersBox.querySelector(`[data-i="${q.correct}"]`);
                if (right) right.classList.add('right');
                meters();
                winner = players.some(p => progress[p.id] >= 100);
                if (winner) {
                    stage.querySelector('.ar-q').textContent = 'FINISH LINE!';
                    ctx.sfx('win');
                }
                ctx.after(winner ? 1400 : 1300, nextRound);
            }

            answersBox.addEventListener('click', e => {
                const btn = e.target.closest('.ar-ans');
                if (btn && !closed && answered[me.id] === undefined) submit(me, Number(btn.dataset.i), (ctx.clock.t - startedAt));
            });
            onKey(ctx, e => {
                const i = 'abcd'.indexOf(e.key.toLowerCase());
                const n = ['1', '2', '3', '4'].indexOf(e.key);
                const index = i >= 0 ? i : n;
                if (index >= 0 && !closed && answered[me.id] === undefined) submit(me, index, ctx.clock.t - startedAt);
            });
            ctx.onRemote('ans', (p, b) => submit(p, b.i, b.ms));
            ctx.onReplaced(p => { const tag = stage.querySelector(`.ar-lane[data-id="${p.id}"] .ar-tag`); if (tag) tag.textContent = p.name; });
            players.forEach(moveBall);
            meters();
            nextRound();
        }
    });

    /* ========================= 5. TRUE OR TRAP ========================= */
    Games.register({
        id: 'true-or-trap', name: 'True or Trap', cover: 'assets/true-or-trap.png',
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const ROUNDS = 10;
            ctx.multiplier = s => (s >= 6 ? 4 : s >= 4 ? 3 : s >= 2 ? 2 : 1);
            const items = GameData.shuffle(GameData.statements).slice(0, ROUNDS);
            stage.innerHTML = `<div class="tt">
                <button class="tt-side true" data-v="1"><span class="ic">&#10003;</span><b>TRUE</b><div class="tt-dots"></div></button>
                <div class="tt-mid"><div class="tt-card"><p></p><small></small></div><div class="tt-bar"><i></i></div></div>
                <button class="tt-side trap" data-v="0"><span class="ic">&#10005;</span><b>TRAP</b><div class="tt-dots"></div></button></div>`;
            const card = stage.querySelector('.tt-card');
            const text = card.querySelector('p');
            const note = card.querySelector('small');
            const bar = stage.querySelector('.tt-bar i');
            const sides = stage.querySelectorAll('.tt-side');

            let round = 0, item = null, limit = 8, answered = {}, choices = {}, closed = true, timerHandle = null, startedAt = 0;

            function nextRound() {
                if (round >= ROUNDS) return ctx.finish();
                item = items[round++];
                ctx.setRound(round, ROUNDS);
                limit = 8 - (round - 1) * 4 / 9;
                answered = {}; choices = {}; closed = false; startedAt = ctx.clock.t;
                text.textContent = item.s;
                note.textContent = '';
                card.classList.remove('right', 'wrong');
                sides.forEach(s => { s.classList.remove('picked', 'correct', 'dim'); s.querySelector('.tt-dots').innerHTML = ''; });
                bar.style.transition = 'none';
                bar.style.width = '100%';
                void bar.offsetWidth;
                bar.style.transition = 'width ' + limit + 's linear';
                bar.style.width = '0%';
                timerHandle = ctx.timer(limit, () => closeRound());
                players.filter(p => p.bot).forEach(bot => {
                    const delay = ctx.botDelay(bot, limit);
                    const right = ctx.botCorrect(bot, 'easy');
                    ctx.after(delay, () => submit(bot, right ? item.t : !item.t, delay));
                });
            }

            function submit(p, value, ms) {
                if (closed || answered[p.id]) return;
                answered[p.id] = true;
                choices[p.id] = value;
                if (p.isYou) ctx.emit('ans', { r: round, v: value, ms });
                const ok = value === item.t;
                const speed = Math.round(500 * Math.max(0, 1 - ms / (limit * 1000)));
                const pts = ctx.award(p.id, ok, 500 + speed, ms);
                if (p.isYou) {
                    ctx.sfx(ok ? 'correct' : 'wrong');
                    sides.forEach(s => s.classList.toggle('picked', (s.dataset.v === '1') === value));
                    card.classList.add(ok ? 'right' : 'wrong');
                    floatText(stage.querySelector('.tt-mid'), ok ? '+' + ctx.fmt(pts) : 'COMBO LOST', ok ? 'good' : 'bad');
                }
                if (players.every(x => answered[x.id])) closeRound();
            }

            function closeRound() {
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                players.forEach(p => { if (!answered[p.id]) ctx.award(p.id, false, 0, limit * 1000); });
                if (players.length > 1) {
                    players.forEach(p => {
                        if (choices[p.id] === undefined) return;
                        const side = stage.querySelector(choices[p.id] ? '.tt-side.true' : '.tt-side.trap');
                        side.querySelector('.tt-dots').insertAdjacentHTML('beforeend', `<i style="background:${p.color}" title="${esc(p.name)}"></i>`);
                    });
                }
                sides.forEach(s => s.classList.add((s.dataset.v === '1') === item.t ? 'correct' : 'dim'));
                note.textContent = item.e;
                ctx.after(1700, nextRound);
            }

            sides.forEach(s => s.addEventListener('click', () => {
                if (!closed && !answered[me.id]) submit(me, s.dataset.v === '1', ctx.clock.t - startedAt);
            }));
            ctx.onRemote('ans', (p, b) => submit(p, !!b.v, b.ms));
            onKey(ctx, e => {
                const k = e.key.toLowerCase();
                if (['arrowleft', 'a', 't'].includes(k) && !closed && !answered[me.id]) submit(me, true, ctx.clock.t - startedAt);
                if (['arrowright', 'd', 'f'].includes(k) && !closed && !answered[me.id]) submit(me, false, ctx.clock.t - startedAt);
            });
            nextRound();
        }
    });

    /* ========================= 4. WHO AM I? ========================= */
    Games.register({
        id: 'who-am-i', name: 'Who Am I?', cover: 'assets/who-am-i.png',
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const ROUNDS = 6, CLUE_MS = 6000, POINTS = [1000, 750, 500, 250];
            const figures = GameData.shuffle(GameData.figures).slice(0, ROUNDS);
            stage.innerHTML = `<div class="wa">
                <div class="wa-left"><div class="wa-sil"><svg viewBox="0 0 120 130"><circle cx="60" cy="34" r="26"/><path d="M8 130c0-38 22-58 52-58s52 20 52 58z"/></svg><b>?</b></div>
                    <div class="wa-card hidden"></div></div>
                <div class="wa-right"><div class="wa-clues"></div><div class="wa-opts"></div></div></div>`;
            const sil = stage.querySelector('.wa-sil');
            const card = stage.querySelector('.wa-card');
            const clueBox = stage.querySelector('.wa-clues');
            const optBox = stage.querySelector('.wa-opts');

            let round = 0, fig = null, unlocked = 1, closed = true, done = {}, plans = [], timerHandle = null, clueTimer = null, startedAt = 0;

            function renderClues() {
                clueBox.innerHTML = fig.clues.map((c, i) => i < unlocked
                    ? `<div class="wa-clue open ${i === unlocked - 1 ? 'fresh' : ''}"><small>CLUE ${i + 1}</small><span>${esc(c)}</span></div>`
                    : `<div class="wa-clue"><small>CLUE ${i + 1}</small><span>&#128274;</span></div>`).join('');
            }

            function nextRound() {
                if (round >= ROUNDS) return ctx.finish();
                fig = figures[round++];
                ctx.setRound(round, ROUNDS);
                unlocked = 1; closed = false; done = {}; startedAt = ctx.clock.t;
                sil.classList.remove('reveal');
                sil.classList.remove('hidden');
                card.classList.add('hidden');
                renderClues();
                const others = GameData.shuffle(GameData.figures.filter(f => f !== fig)).slice(0, 3);
                const names = GameData.shuffle([fig.name, ...others.map(o => o.name)]);
                optBox.innerHTML = names.map(n => `<button class="wa-opt" data-n="${esc(n)}">${esc(n)}</button>`).join('');
                clueTimer = ctx.every(CLUE_MS, () => { if (unlocked < 4) { unlocked++; renderClues(); ctx.sfx('tick'); } });
                timerHandle = ctx.timer(CLUE_MS * 4 / 1000 + 6, () => closeRound());
                plans = players.filter(p => p.bot).map(bot => {
                    const stageNo = Math.max(1, Math.min(4, Math.round(bot.bot.spd / 2 + Math.random() * 1.2 - 0.6)));
                    const at = (stageNo - 1) * CLUE_MS + 1500 + Math.random() * 3000;
                    const right = Math.random() < Math.min(0.95, bot.bot.acc + 0.05 * stageNo);
                    const plan = { bot, stageNo, right, at, applied: false };
                    ctx.after(at, () => applyPlan(plan));
                    return plan;
                });
            }

            function applyPlan(plan) {
                if (plan.applied || done[plan.bot.id]) return;
                plan.applied = true;
                done[plan.bot.id] = true;
                if (plan.right) ctx.award(plan.bot.id, true, POINTS[plan.stageNo - 1], plan.at);
                else { ctx.stats[plan.bot.id].combo = 0; ctx.addScore(plan.bot.id, -200); ctx.award(plan.bot.id, false, 0, plan.at); }
            }

            function guess(name, button) {
                if (closed || done[me.id]) return;
                const ok = name === fig.name;
                ctx.emit('guess', { r: round, ok, st: unlocked, ms: ctx.clock.t - startedAt });
                if (ok) {
                    done[me.id] = true;
                    ctx.award(me.id, true, POINTS[unlocked - 1], ctx.clock.t - startedAt);
                    ctx.sfx('correct');
                    button.classList.add('right');
                    floatText(optBox, '+' + ctx.fmt(POINTS[unlocked - 1]), 'good');
                    closeRound();
                } else {
                    ctx.stats[me.id].combo = 0;
                    ctx.addScore(me.id, -200);
                    ctx.refresh();
                    ctx.sfx('wrong');
                    button.classList.add('wrong');
                    button.disabled = true;
                    floatText(optBox, '-200', 'bad');
                }
            }

            function closeRound() {
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                ctx.cancel(clueTimer);
                plans.forEach(applyPlan);
                players.forEach(p => { if (!done[p.id]) { done[p.id] = true; ctx.award(p.id, false, 0, CLUE_MS * 4); } });
                optBox.querySelectorAll('.wa-opt').forEach(b => { b.disabled = true; if (b.dataset.n === fig.name) b.classList.add('right'); });
                sil.classList.add('reveal');
                card.innerHTML = `<div class="name">${esc(fig.name)}</div><div class="years">${esc(fig.years)}</div><p>${esc(fig.fact)}</p>`;
                card.classList.remove('hidden');
                ctx.after(2600, nextRound);
            }

            optBox.addEventListener('click', e => {
                const btn = e.target.closest('.wa-opt');
                if (btn && !btn.disabled) guess(btn.dataset.n, btn);
            });
            ctx.onRemote('guess', (p, b) => {
                if (closed || done[p.id]) return;
                if (b.ok) { done[p.id] = true; ctx.award(p.id, true, POINTS[Math.max(0, Math.min(3, (b.st || 1) - 1))], b.ms); }
                else { ctx.stats[p.id].combo = 0; ctx.addScore(p.id, -200); }
            });
            nextRound();
        }
    });

    /* ========================= 8. HISTORY MILLIONAIRE ========================= */
    Games.register({
        id: 'history-millionaire', name: 'History Millionaire', cover: 'assets/history-millionaire.png',
        run(ctx) {
            const { stage, players, me, esc } = ctx;
            const PRIZES = [100, 200, 400, 800, 1600, 3200, 6400, 12500, 25000, 50000, 75000, 125000, 250000, 500000, 1000000];
            const SAFE = [5, 10];
            const LIMIT = 30;
            const used = [];
            const tier = (n, d) => { const list = ctx.questions(n, { difficulty: d, exclude: used }); list.forEach(q => used.push(q.id)); return list; };
            const plan = [...tier(6, 'easy'), ...tier(6, 'medium'), ...tier(6, 'hard')];
            const spare = { easy: plan.slice(5, 6), medium: plan.slice(11, 12), hard: plan.slice(17, 18) };
            const ladder = [...plan.slice(0, 5), ...plan.slice(6, 11), ...plan.slice(12, 17)];
            const lifelines = { five: true, hint: true, skip: true, audience: true };
            const prize = lvl => (lvl > 0 ? PRIZES[lvl - 1] : 0);
            const safePrize = lvl => { const s = SAFE.filter(x => x < lvl).pop() || 0; return prize(s); };
            const botState = {};
            players.filter(p => p.bot).forEach(b => { botState[b.id] = { level: 0, out: false }; });

            stage.innerHTML = `<div class="mm"><div class="mm-main">
                <div class="mm-life"><button data-l="five">50:50</button><button data-l="hint">HINT</button><button data-l="skip">SKIP</button><button data-l="audience">AUDIENCE</button></div>
                <div class="mm-q"></div><div class="mm-hint hidden"></div><div class="mm-aud hidden"></div><div class="mm-answers"></div></div>
                <div class="mm-ladder">${PRIZES.map((v, i) => `<div class="mm-step ${SAFE.includes(i + 1) ? 'safe' : ''}" data-l="${i + 1}"><span>${i + 1}</span><b>${ctx.fmt(v)}</b></div>`).reverse().join('')}</div></div>`;
            const qBox = stage.querySelector('.mm-q');
            const ansBox = stage.querySelector('.mm-answers');
            const hintBox = stage.querySelector('.mm-hint');
            const audBox = stage.querySelector('.mm-aud');

            let level = 1, q = null, closed = true, timerHandle = null, startedAt = 0, removed = [];

            function highlight() {
                const ladderBox = stage.querySelector('.mm-ladder');
                stage.querySelectorAll('.mm-step').forEach(s => {
                    const l = Number(s.dataset.l);
                    s.classList.toggle('current', l === level);
                    s.classList.toggle('passed', l < level);
                    if (l === level && ladderBox.scrollWidth > ladderBox.clientWidth) {
                        ladderBox.scrollLeft = s.offsetLeft - ladderBox.clientWidth / 2 + s.offsetWidth / 2;
                    }
                });
            }

            function show(question) {
                q = question; closed = false; removed = []; startedAt = ctx.clock.t;
                ctx.setRound(level, 15);
                qBox.textContent = q.question;
                hintBox.classList.add('hidden'); audBox.classList.add('hidden');
                ansBox.innerHTML = q.answers.map((a, i) => `<button class="mm-ans" data-i="${i}"><b>${LETTERS[i]}</b><span>${esc(a)}</span></button>`).join('');
                highlight();
                ctx.cancel(timerHandle);
                timerHandle = ctx.timer(LIMIT, () => choose(-1));
            }

            function stepBots(alive) {
                Object.keys(botState).forEach(id => {
                    const st = botState[id];
                    if (st.out) return;
                    const bot = players.find(p => p.id === id);
                    const chance = Math.max(0.25, bot.bot.acc - (level / 15) * 0.35);
                    if (alive && Math.random() < chance) st.level = level;
                    else st.out = true;
                    const s = ctx.stats[id];
                    s.score = st.out ? safePrize(level) : prize(st.level);
                    s.correct = st.level;
                    s.total = st.level + (st.out ? 1 : 0);
                    s.best = st.level;
                });
                ctx.refresh();
            }

            function choose(index) {
                if (closed) return;
                closed = true;
                ctx.cancel(timerHandle);
                const ms = ctx.clock.t - startedAt;
                const ok = index === q.correct;
                ansBox.querySelectorAll('.mm-ans').forEach(b => { b.disabled = true; });
                const picked = ansBox.querySelector(`[data-i="${index}"]`);
                if (picked) picked.classList.add('locked');
                ctx.after(900, () => {
                    ansBox.querySelector(`[data-i="${q.correct}"]`).classList.add('right');
                    if (picked && !ok) picked.classList.add('wrong');
                    ctx.sfx(ok ? 'correct' : 'wrong');
                    ctx.award(me.id, ok, 0, ms);
                    if (ok) {
                        ctx.stats[me.id].score = prize(level);
                        ctx.refresh();
                    }
                    stepBots(ok);
                    ctx.emit('lvl', { level: ok ? level : level - 1, out: !ok, prize: ok ? prize(level) : safePrize(level) });
                    ctx.after(1500, () => {
                        if (!ok) {
                            ctx.stats[me.id].score = safePrize(level);
                            qBox.textContent = 'You leave with ' + ctx.fmt(safePrize(level));
                            return end();
                        }
                        if (level === 15) { ctx.confetti(120); ctx.sfx('win'); qBox.textContent = 'HISTORY MILLIONAIRE!'; return end(); }
                        level++;
                        show(ladder[level - 1]);
                    });
                });
            }

            function end() {
                Object.keys(botState).forEach(id => { botState[id].out = true; });
                players.forEach(p => { ctx.stats[p.id].score = Math.round(ctx.stats[p.id].score / 100); });
                ctx.refresh();
                ctx.after(1600, () => ctx.finish());
            }

            function useLife(name) {
                if (closed || !lifelines[name]) return;
                lifelines[name] = false;
                stage.querySelector(`.mm-life [data-l="${name}"]`).disabled = true;
                if (name === 'five') {
                    const wrong = [0, 1, 2, 3].filter(i => i !== q.correct);
                    removed = GameData.shuffle(wrong).slice(0, 2);
                    removed.forEach(i => { const b = ansBox.querySelector(`[data-i="${i}"]`); b.disabled = true; b.classList.add('gone'); });
                } else if (name === 'hint') {
                    hintBox.textContent = 'Hint: ' + q.hint;
                    hintBox.classList.remove('hidden');
                } else if (name === 'skip') {
                    const d = level <= 5 ? 'easy' : level <= 10 ? 'medium' : 'hard';
                    const replacement = spare[d].pop() || ctx.questions(1, { difficulty: d, exclude: used })[0];
                    ladder[level - 1] = replacement;
                    show(replacement);
                } else if (name === 'audience') {
                    const strength = level <= 5 ? 0.7 : level <= 10 ? 0.55 : 0.4;
                    const right = Math.round((strength + Math.random() * 0.2) * 100);
                    const others = [0, 1, 2, 3].filter(i => i !== q.correct && removed.indexOf(i) === -1);
                    let rest = 100 - right;
                    const shares = {};
                    others.forEach((i, k) => { const s = k === others.length - 1 ? rest : Math.round(rest * Math.random() * 0.6); shares[i] = s; rest -= s; });
                    shares[q.correct] = right;
                    audBox.innerHTML = [0, 1, 2, 3].map(i => `<div><small>${LETTERS[i]}</small><i style="height:${shares[i] || 0}%"></i><b>${shares[i] || 0}%</b></div>`).join('');
                    audBox.classList.remove('hidden');
                }
            }

            ctx.onRemote('lvl', (p, b) => {
                const s = ctx.stats[p.id];
                s.score = b.prize; s.correct = b.level; s.total = b.level + (b.out ? 1 : 0); s.best = b.level;
                ctx.refresh();
            });
            ctx.onReplaced(p => { botState[p.id] = { level: ctx.stats[p.id].correct, out: false }; });
            stage.querySelector('.mm-life').addEventListener('click', e => { const b = e.target.closest('button'); if (b) useLife(b.dataset.l); });
            ansBox.addEventListener('click', e => { const b = e.target.closest('.mm-ans'); if (b && !b.disabled) choose(Number(b.dataset.i)); });
            onKey(ctx, e => { const i = 'abcd'.indexOf(e.key.toLowerCase()); const b = ansBox.querySelector(`[data-i="${i}"]`); if (i >= 0 && b && !b.disabled) choose(i); });
            show(ladder[0]);
        }
    });
})();
