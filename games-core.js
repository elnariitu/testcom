/* Shared game engine: page, HUD, clock, scoring, countdown, pause, results, confetti, sound.
   Players are local (you + AI). The `ctx` object handed to each game is the only thing a game talks to,
   so a WebSocket provider can later replace the AI without touching the game code. */
(function () {
    'use strict';

    const $ = (sel, root) => (root || document).querySelector(sel);
    const el = (tag, cls, html) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (html != null) node.innerHTML = html;
        return node;
    };
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
    const rand = (a, b) => a + Math.random() * (b - a);
    const pick = arr => arr[Math.floor(Math.random() * arr.length)];
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fmt = n => Math.round(n).toLocaleString('en-US');
    const mmss = s => { s = Math.max(0, Math.ceil(s)); return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); };

    const COLORS = ['#3b82f6', '#ef4444', '#22c55e', '#f5b301'];
    const BOT_NAMES = ['Aigerim', 'Dauren', 'Madina', 'Arman', 'Aliya', 'Yerlan', 'Dana', 'Timur'];
    const XP_KEY = 'testcomXP';
    const XP_PER_LEVEL = 3000;

    /* ---------- Sound (tiny WebAudio tones, follows the site's mute button) ---------- */
    const Sfx = {
        audio: null,
        tone(freq, start, len, type, vol) {
            try {
                if (typeof bgMusic !== 'undefined' && bgMusic.muted) return;
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return;
                this.audio = this.audio || new AC();
                const t0 = this.audio.currentTime + start;
                const osc = this.audio.createOscillator();
                const gain = this.audio.createGain();
                osc.type = type || 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.0001, t0);
                gain.gain.exponentialRampToValueAtTime(vol || 0.12, t0 + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, t0 + len);
                osc.connect(gain);
                gain.connect(this.audio.destination);
                osc.start(t0);
                osc.stop(t0 + len + 0.05);
            } catch (e) {}
        },
        play(name) {
            if (name === 'correct') { this.tone(660, 0, 0.12); this.tone(990, 0.1, 0.18); }
            else if (name === 'wrong') { this.tone(180, 0, 0.25, 'sawtooth', 0.09); }
            else if (name === 'tick') { this.tone(800, 0, 0.05, 'square', 0.05); }
            else if (name === 'go') { this.tone(880, 0, 0.3, 'triangle', 0.15); }
            else if (name === 'win') { [523, 659, 784, 1047].forEach((f, i) => this.tone(f, i * 0.12, 0.25, 'triangle', 0.13)); }
            else if (name === 'boost') { this.tone(400, 0, 0.1, 'square', 0.07); this.tone(800, 0.08, 0.15, 'square', 0.07); }
        }
    };

    /* ---------- Game clock: everything a game schedules stops while paused ---------- */
    function Clock() {
        this.t = 0;
        this.paused = false;
        this.items = [];
        this.id = setInterval(() => this.tick(), 50);
    }
    let speedFactor = 1;   // 1 = normal; higher only for automated tests

    Clock.prototype.tick = function () {
        if (this.paused) return;
        this.t += 50 * speedFactor;
        for (const it of this.items.slice()) {
            if (it.dead) continue;
            if (it.every) {
                while (!it.dead && it.at <= this.t) { it.at += it.every; it.fn(); }
            } else if (this.t >= it.at) {
                it.dead = true;
                it.fn();
            }
        }
        this.items = this.items.filter(it => !it.dead);
    };
    Clock.prototype.after = function (ms, fn) { const it = { at: this.t + ms, fn }; this.items.push(it); return it; };
    Clock.prototype.every = function (ms, fn) { const it = { at: this.t + ms, every: ms, fn }; this.items.push(it); return it; };
    Clock.prototype.cancel = function (it) { if (it) it.dead = true; };
    Clock.prototype.destroy = function () { clearInterval(this.id); this.items = []; };

    /* ---------- Page ---------- */
    let page = null;
    let refs = {};
    let current = null;      // { game, opts, ctx }
    let hooks = { onChangeGame() {}, onHome() {}, nick() { return 'Player'; } };

    function buildPage() {
        if (page) return;
        page = el('div', 'game-page hidden');
        page.id = 'game-page';
        page.innerHTML = `
            <div class="gp-bg"></div>
            <div class="gp-hud">
                <button class="gp-pause" aria-label="Pause">&#10074;&#10074;</button>
                <div class="gp-round">Round 1 / 10</div>
                <div class="gp-timer">00:00</div>
            </div>
            <div class="gp-sub">
                <span class="gp-score">Score: <b>0</b></span>
                <span class="gp-combo">Combo: <b>x1</b></span>
            </div>
            <div class="gp-board"></div>
            <div class="gp-stage"></div>
            <div class="gp-overlay hidden"></div>`;
        document.body.appendChild(page);
        refs = {
            pause: $('.gp-pause', page), round: $('.gp-round', page), timer: $('.gp-timer', page),
            score: $('.gp-score b', page), combo: $('.gp-combo b', page), comboBox: $('.gp-combo', page),
            board: $('.gp-board', page), stage: $('.gp-stage', page), overlay: $('.gp-overlay', page)
        };
        refs.pause.addEventListener('click', () => current && current.ctx.pause());
    }

    /* ---------- Confetti ---------- */
    function confetti(count) {
        const layer = el('div', 'gp-confetti');
        const colors = ['#f5b301', '#3b82f6', '#ef4444', '#22c55e', '#a855f7', '#ffffff'];
        for (let i = 0; i < (count || 70); i++) {
            const piece = el('i');
            piece.style.left = rand(0, 100) + '%';
            piece.style.background = pick(colors);
            piece.style.animationDelay = rand(0, 0.8) + 's';
            piece.style.animationDuration = rand(2.2, 4) + 's';
            piece.style.setProperty('--drift', rand(-80, 80) + 'px');
            layer.appendChild(piece);
        }
        page.appendChild(layer);
        setTimeout(() => layer.remove(), 5200);
    }

    /* ---------- Context given to a game ---------- */
    function makeContext(game, opts) {
        const clock = new Clock();
        const cleanups = [];
        const players = opts.players.map((p, i) => Object.assign({ color: COLORS[i % COLORS.length], id: 'p' + i }, p));
        const stats = {};
        players.forEach(p => { stats[p.id] = { score: 0, combo: 0, best: 0, correct: 0, total: 0, ms: 0 }; });
        const me = players.find(p => p.isYou) || players[0];
        let rounds = 0;
        let ended = false;

        /* Online match: other humans' actions arrive as events; `r` is the round they belong to */
        const online = opts.online || null;
        const handlers = {};
        const buffered = [];
        const finals = {};
        const replaceHooks = [];
        let curRound = 0;
        let pollTimer = null;
        let polling = false;

        function dispatch(e) {
            const p = players.find(x => x.slot === e.slot);
            if (!p || p.isYou) return;
            if (e.kind === 'final') { finals[p.slot] = e.body; return; }
            if (e.kind === 'leave') { if (!finals[p.slot] && p.remote) ctx.replaceWithAI(p); return; }
            if (!p.remote) return;
            const b = e.body || {};
            if (b.r !== undefined) {
                if (b.r > curRound) { buffered.push(e); return; }
                if (b.r < curRound) return;
            }
            const fn = handlers[e.kind];
            if (fn) fn(p, b);
        }

        function flushRound() {
            const waiting = buffered.splice(0);
            waiting.forEach(e => dispatch(e));
        }

        const ctx = {
            stage: refs.stage, players, me, stats, clock, game, opts,
            get paused() { return clock.paused; },
            after: (ms, fn) => clock.after(ms, fn),
            every: (ms, fn) => clock.every(ms, fn),
            cancel: it => clock.cancel(it),
            onCleanup: fn => cleanups.push(fn),
            el, esc, rand, pick, clamp, fmt, sfx: n => Sfx.play(n), confetti,
            shuffle: list => GameData.shuffle(list),
            questions: (n, o) => GameData.pickQuestions(n, o),
            rng: () => GameData.rand(),
            online: !!online,
            emit(kind, body) { if (online) Net.emit(kind, body); },
            onRemote(kind, fn) { handlers[kind] = fn; },

            startOnline() {
                if (!online) return;
                Net.resetEvents();
                pollTimer = setInterval(async () => {
                    if (polling) return;
                    polling = true;
                    const list = await Net.events();
                    polling = false;
                    list.forEach(dispatch);
                }, 500);
            },

            /* A human left the match: an AI player takes over the place */
            replaceWithAI(p) {
                if (!p.remote) return;
                p.remote = false;
                p.bot = { acc: rand(0.55, 0.85), spd: rand(2.8, 5.5) };
                const taken = players.map(x => x.name);
                p.name = BOT_NAMES.filter(n => taken.indexOf(n) === -1)[0] || 'Rival';
                p.replaced = true;
                ctx.refresh();
                replaceHooks.forEach(fn => { try { fn(p); } catch (e) {} });
            },
            onReplaced(fn) { replaceHooks.push(fn); },

            setRound(i, n) { rounds = n; curRound = i; refs.round.textContent = 'Round ' + i + ' / ' + n; if (online) setTimeout(flushRound, 0); },
            setTime(sec) { refs.timer.textContent = mmss(sec); refs.timer.classList.toggle('urgent', sec <= 3 && sec > 0); },
            setTitle(text) { refs.round.textContent = text; },

            /* Countdown timer for a round: onTick(secondsLeft), onEnd() */
            timer(seconds, onEnd, onTick) {
                let left = seconds;
                ctx.setTime(left);
                const handle = clock.every(100, () => {
                    left = Math.max(0, left - 0.1);
                    ctx.setTime(left);
                    if (onTick) onTick(left);
                    if (left <= 0) { clock.cancel(handle); handle.left = 0; onEnd(); }
                });
                handle.left = () => left;
                return handle;
            },

            /* Combo multiplier: x2 after 2 in a row ... x5 after 5 */
            multiplier(streak) { return streak >= 5 ? 5 : streak >= 4 ? 4 : streak >= 3 ? 3 : streak >= 2 ? 2 : 1; },

            /* Register an answer. points = base score if correct. Returns the points really added. */
            award(pid, correct, points, ms) {
                const s = stats[pid];
                s.total++;
                s.ms += ms || 0;
                let added = 0;
                if (correct) {
                    s.combo++;
                    s.correct++;
                    s.best = Math.max(s.best, s.combo);
                    added = Math.round(points * (1 + (ctx.multiplier(s.combo) - 1) * 0.2));
                    s.score += added;
                } else {
                    s.combo = 0;
                    if (points < 0) s.score = Math.max(0, s.score + points);
                }
                ctx.refresh();
                return added;
            },
            addScore(pid, points) { stats[pid].score = Math.max(0, stats[pid].score + points); ctx.refresh(); },

            refresh() {
                const mine = stats[me.id];
                refs.score.textContent = fmt(mine.score);
                const mult = ctx.multiplier(mine.combo);
                refs.combo.textContent = 'x' + mult;
                refs.comboBox.classList.toggle('hot', mult > 1);
                const ranked = players.slice().sort((a, b) => stats[b.id].score - stats[a.id].score);
                refs.board.innerHTML = players.length < 2 ? '' : ranked.map((p, i) =>
                    `<div class="gp-row ${p.isYou ? 'me' : ''}"><span class="gp-rank">${i + 1}</span><i style="background:${p.color}"></i><span class="gp-name">${esc(p.name)}${p.bot ? ' <em>AI</em>' : ''}</span><b>${fmt(stats[p.id].score)}</b></div>`).join('');
            },

            /* Bot helpers */
            botDelay(p, limitSec) {
                const base = (p.bot ? p.bot.spd : 4) * 1000;
                return clamp(base + rand(-1400, 1400), 900, limitSec * 1000 - 600);
            },
            botCorrect(p, difficulty) {
                const penalty = difficulty === 'hard' ? 0.18 : difficulty === 'medium' ? 0.08 : 0;
                return Math.random() < clamp((p.bot ? p.bot.acc : 0.7) - penalty, 0.2, 0.95);
            },

            pause() {
                if (ended || clock.paused || refs.overlay.querySelector('.gp-panel')) return;
                if (!online) {
                    clock.paused = true;
                    page.classList.add('paused');
                }
                refs.overlay.classList.remove('hidden');
                refs.overlay.innerHTML = `<div class="gp-panel"><h2>PAUSED</h2>
                    ${online ? '<p class="gp-note">Online match: the game keeps running for the others. If you quit, an AI player takes your place.</p>' : ''}
                    <button class="gp-btn primary" data-act="resume">RESUME</button>
                    <button class="gp-btn" data-act="quit">QUIT TO LOBBY</button></div>`;
                refs.overlay.onclick = e => {
                    const act = e.target.dataset && e.target.dataset.act;
                    if (act === 'resume') ctx.resume();
                    if (act === 'quit') { Games.stop(); hooks.onChangeGame(); }
                };
            },
            resume() {
                clock.paused = false;
                page.classList.remove('paused');
                refs.overlay.classList.add('hidden');
                refs.overlay.innerHTML = '';
                if (game.onResume) game.onResume(ctx);
            },

            finish() {
                if (ended) return;
                ended = true;
                clock.after(600, () => {
                    if (!online) { showResults(ctx); return; }
                    refs.overlay.classList.remove('hidden');
                    refs.overlay.onclick = null;
                    refs.overlay.innerHTML = '<div class="gp-panel"><h2>WAITING FOR PLAYERS...</h2></div>';
                    const s = stats[me.id];
                    Net.emit('final', { score: Math.round(s.score), correct: s.correct, total: s.total, best: s.best, ms: s.ms });
                    const started = Date.now();
                    const wait = setInterval(() => {
                        const pending = players.some(p => p.remote && !finals[p.slot]);
                        if (pending && Date.now() - started < 8000) return;
                        clearInterval(wait);
                        players.forEach(p => {
                            const f = finals[p.slot];
                            if (f && p.remote) Object.assign(stats[p.id], { score: f.score, correct: f.correct, total: f.total, best: f.best, ms: f.ms });
                        });
                        Net.call('leave');
                        showResults(ctx);
                    }, 300);
                });
            },
            destroy() {
                ended = true;
                clearInterval(pollTimer);
                cleanups.forEach(fn => { try { fn(); } catch (e) {} });
                clock.destroy();
            }
        };
        return ctx;
    }

    /* ---------- Countdown 3 - 2 - 1 - GO ---------- */
    function countdown(done) {
        let n = 3;
        refs.overlay.classList.remove('hidden');
        refs.overlay.onclick = null;
        const show = text => {
            refs.overlay.innerHTML = `<div class="gp-count ${text === 'GO!' ? 'go' : ''}"><span>${text}</span></div>`;
        };
        show(n);
        Sfx.play('tick');
        const id = setInterval(() => {
            n--;
            if (n > 0) { show(n); Sfx.play('tick'); }
            else if (n === 0) { show('GO!'); Sfx.play('go'); }
            else {
                clearInterval(id);
                refs.overlay.classList.add('hidden');
                refs.overlay.innerHTML = '';
                done();
            }
        }, 800);
        current.cancelCountdown = () => clearInterval(id);
    }

    /* ---------- Results ---------- */
    function readXP() { try { return Number(localStorage.getItem(XP_KEY)) || 0; } catch (e) { return 0; } }
    function writeXP(v) { try { localStorage.setItem(XP_KEY, String(v)); } catch (e) {} }

    function showResults(ctx) {
        const ranked = ctx.players.slice().sort((a, b) => ctx.stats[b.id].score - ctx.stats[a.id].score);
        const mine = ctx.stats[ctx.me.id];
        const myRank = ranked.indexOf(ctx.me) + 1;
        const before = readXP();
        const gained = Math.round(mine.score);
        writeXP(before + gained);
        const level = xp => Math.floor(xp / XP_PER_LEVEL) + 1;
        const pct = xp => (xp % XP_PER_LEVEL) / XP_PER_LEVEL * 100;
        const ordinal = n => ['1st', '2nd', '3rd', '4th'][n - 1] || n + 'th';
        const accuracy = mine.total ? Math.round(mine.correct / mine.total * 100) : 0;
        const avg = mine.total ? (mine.ms / mine.total / 1000).toFixed(1) : '0.0';

        ctx.setTitle('Finished');
        ctx.setTime(0);
        refs.overlay.classList.remove('hidden');
        refs.overlay.onclick = null;
        refs.overlay.innerHTML = `
            <div class="gp-panel results">
                <div class="gp-trophy">&#127942;</div>
                <h2>${myRank === 1 && mine.score > 0 ? 'VICTORY!' : 'GAME COMPLETE'}</h2>
                <div class="gp-ranks">
                    ${ranked.map((p, i) => `<div class="gp-rankrow ${p.isYou ? 'me' : ''}" style="animation-delay:${i * 0.12}s">
                        <span class="pos">${ordinal(i + 1)}</span><i style="background:${p.color}"></i>
                        <span class="nm">${esc(p.name)}${p.bot ? ' <em>AI</em>' : ''}</span><b data-xp="${Math.round(ctx.stats[p.id].score)}">0</b><small>XP</small></div>`).join('')}
                </div>
                <div class="gp-stats">
                    <div><b>${mine.correct} / ${mine.total}</b><small>Correct</small></div>
                    <div><b>${accuracy}%</b><small>Accuracy</small></div>
                    <div><b>x${Math.max(1, ctx.multiplier(mine.best))}</b><small>Best combo (${mine.best})</small></div>
                    <div><b>${avg}s</b><small>Avg response</small></div>
                </div>
                <div class="gp-level"><span>LEVEL ${level(before)}</span><div class="bar"><i style="width:${pct(before)}%"></i></div><span>+${fmt(gained)} XP</span></div>
                <div class="gp-actions">
                    <button class="gp-btn primary" data-act="again">PLAY AGAIN</button>
                    <button class="gp-btn" data-act="change">CHANGE GAME</button>
                    <button class="gp-btn" data-act="home">HOME</button>
                </div>
            </div>`;
        refs.overlay.querySelectorAll('b[data-xp]').forEach(node => {
            const target = Number(node.dataset.xp);
            const start = Date.now();
            const id = setInterval(() => {
                const p = Math.min(1, (Date.now() - start) / 1100);
                node.textContent = fmt(target * (1 - Math.pow(1 - p, 3)));
                if (p >= 1) clearInterval(id);
            }, 40);
        });
        setTimeout(() => {
            const bar = $('.gp-level .bar i', refs.overlay);
            if (bar) {
                const after = before + gained;
                bar.style.width = (level(after) > level(before) ? 100 : pct(after)) + '%';
            }
        }, 200);
        if (myRank === 1 && mine.score > 0) { confetti(90); Sfx.play('win'); }
        refs.overlay.onclick = e => {
            const act = e.target.dataset && e.target.dataset.act;
            if (act === 'again') Games.replay();
            if (act === 'change') { Games.stop(); hooks.onChangeGame(); }
            if (act === 'home') { Games.stop(); hooks.onHome(); }
        };
    }

    /* ---------- Public API ---------- */
    const registry = [];

    const Games = {
        register(game) { registry.push(game); },
        list() { return registry.slice(); },
        get(id) { return registry.find(g => g.id === id); },
        config(h) { Object.assign(hooks, h); },
        colors: COLORS,
        botNames(seed) { const start = Math.abs(Number(seed) || 0) % BOT_NAMES.length; return BOT_NAMES.map((_, i) => BOT_NAMES[(start + i) % BOT_NAMES.length]); },

        /* Builds the player list for a match. `names` are the found players from the lobby (you first). */
        makePlayers(size, names) {
            const list = [{ name: hooks.nick(), isYou: true }];
            const bots = BOT_NAMES.slice().sort(() => Math.random() - 0.5);
            const wanted = Math.max(1, size);
            for (let i = 1; i < wanted; i++) {
                list.push({ name: (names && names[i]) || bots[i], bot: { acc: rand(0.55, 0.88), spd: rand(2.6, 6) } });
            }
            return list;
        },

        launch(id, opts) {
            const game = Games.get(id);
            if (!game) return;
            Games.stop();
            buildPage();
            opts = Object.assign({ size: 1 }, opts);
            opts.players = opts.players || Games.makePlayers(opts.size);
            if (opts.players.length === 1 && game.needsRival) {
                opts.players = opts.players.concat([{ name: pick(BOT_NAMES), bot: { acc: rand(0.6, 0.85), spd: rand(3, 5) } }]);
            }
            page.classList.remove('hidden', 'paused');
            page.dataset.game = id;
            refs.stage.innerHTML = '';
            refs.overlay.classList.add('hidden');
            refs.overlay.innerHTML = '';
            if (opts.online) GameData.seed(opts.online.seed);
            const ctx = makeContext(game, opts);
            current = { game, opts, ctx };
            ctx.setTitle(game.name.toUpperCase());
            ctx.setTime(0);
            ctx.refresh();
            countdown(() => { ctx.startOnline(); game.run(ctx); });
        },

        replay() {
            if (!current) return;
            const { game, opts } = current;
            if (opts.online) { Games.stop(); hooks.onChangeGame(); return; }
            Games.launch(game.id, { size: opts.size, players: opts.players.map(p => ({ name: p.name, isYou: p.isYou, bot: p.bot ? { acc: rand(0.55, 0.88), spd: rand(2.6, 6) } : undefined })) });
        },

        stop() {
            if (!current) return;
            if (current.cancelCountdown) current.cancelCountdown();
            if (current.opts.online) { Net.call('leave'); GameData.seed(null); }
            current.ctx.destroy();
            current = null;
            if (page) {
                page.classList.add('hidden');
                refs.stage.innerHTML = '';
                refs.overlay.classList.add('hidden');
                refs.overlay.innerHTML = '';
                page.querySelectorAll('.gp-confetti').forEach(n => n.remove());
            }
        },

        isRunning() { return !!current; },
        speed(n) { speedFactor = n || 1; },
        debug() { return current && current.ctx; }
    };

    window.Games = Games;
})();
