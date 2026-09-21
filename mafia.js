/* Mafia: the whole client (menu, room lobby, game table, chat, results). Everything the player reads is Russian.
   The server decides everything (mafia.php); this file only shows the view it receives and sends actions. */
(function () {
    'use strict';

    const API = 'mafia.php';
    const COLORS = ['#3b82f6', '#ef4444', '#22c55e', '#eab308', '#a855f7', '#06b6d4', '#f97316', '#ec4899', '#94a3b8', '#84cc16'];
    const COLOR_NAMES = ['Синий', 'Красный', 'Зелёный', 'Золотой', 'Фиолетовый', 'Бирюзовый', 'Оранжевый', 'Розовый', 'Серый', 'Лаймовый'];
    const ROLES = {
        mafia: { icon: '🔴', name: 'МАФИЯ', cls: 'mafia', text: 'Устраните мирных жителей и останьтесь незамеченным.' },
        civilian: { icon: '🔵', name: 'МИРНЫЙ ЖИТЕЛЬ', cls: 'civilian', text: 'Найдите мафию вместе с другими жителями города.' },
        doctor: { icon: '🟢', name: 'ДОКТОР', cls: 'doctor', text: 'Каждую ночь вы можете защитить одного игрока.' },
        detective: { icon: '🟣', name: 'ДЕТЕКТИВ', cls: 'detective', text: 'Каждую ночь вы можете проверить одного игрока.' }
    };
    const PHASE_LABEL = {
        ROLE_REVEAL: 'РАСПРЕДЕЛЕНИЕ РОЛЕЙ', NIGHT: 'НОЧЬ', NIGHT_RESOLUTION: 'НОЧЬ', MORNING: 'УТРО', DISCUSSION: 'ОБСУЖДЕНИЕ',
        VOTING: 'ГОЛОСОВАНИЕ', REVOTE: 'ПЕРЕГОЛОСОВАНИЕ', ELIMINATION: 'ИТОГИ ГОЛОСОВАНИЯ', GAME_OVER: 'ИГРА ОКОНЧЕНА', LOBBY: 'КОМНАТА'
    };
    const MODES = [
        { id: 'duo', title: 'ДУО', sub: '2 игрока + ИИ', size: 6 },
        { id: 'squad', title: 'СКВАД', sub: '4 игрока', size: 4 },
        { id: 'six', title: '6 ИГРОКОВ', sub: 'Классическая игра', size: 6 },
        { id: 'ten', title: '10 ИГРОКОВ', sub: 'Большая онлайн-игра', size: 10 }
    ];
    const ROLE_SETUP = {
        4: '1 мафия, 3 мирных жителя',
        6: '1 мафия, 1 доктор, 1 детектив, 3 мирных жителя',
        10: '2 мафии, 1 доктор, 1 детектив, 6 мирных жителей'
    };

    /* ---------- state ---------- */
    const S = {
        open: false, screen: '', view: null, hub: null, offset: 0, pt: null, hubAt: 0, sel: null, busy: false,
        chatSeen: {}, eventSeen: {}, sig: '', lastPhase: '', lastPid: 0, dialog: null, netBad: false, tab: 'chat',
        settings: { sound: true, music: true, anim: true }
    };
    try { Object.assign(S.settings, JSON.parse(localStorage.getItem('mafiaSettings') || '{}')); } catch (e) { /* private mode */ }

    /* ---------- sound hooks: nothing is bundled; register(name, url) later to enable a sound ---------- */
    const SOUND_FILES = {};   // e.g. SOUND_FILES.night = 'assets/mafia/night.mp3'
    const sound = {
        register(name, url) { SOUND_FILES[name] = url; },
        play(name) {
            if (!S.settings.sound || !SOUND_FILES[name]) return;
            try { const a = new Audio(SOUND_FILES[name]); a.volume = 0.6; a.play().catch(() => {}); } catch (e) { /* ignore */ }
        }
    };
    let ambience = null;
    function syncAmbience() {   // night ambience / music hook
        const want = S.open && S.settings.music && S.view && S.view.phase === 'NIGHT' && SOUND_FILES.ambience;
        if (want && !ambience) { try { ambience = new Audio(SOUND_FILES.ambience); ambience.loop = true; ambience.volume = 0.35; ambience.play().catch(() => {}); } catch (e) { /* ignore */ } }
        if (!want && ambience) { ambience.pause(); ambience = null; }
    }

    /* ---------- helpers ---------- */
    const $ = (sel, root) => (root || page).querySelector(sel);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const color = seat => COLORS[seat % COLORS.length];
    const initial = name => esc(String(name || '?').trim().charAt(0).toUpperCase());
    const fmt = sec => { sec = Math.max(0, Math.ceil(sec)); return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0'); };
    const seatOf = seat => S.view.seats[seat];
    const nameOf = seat => (S.view.seats[seat] && !S.view.seats[seat].empty) ? S.view.seats[seat].name : '?';

    let page = null;

    function saveSettings() { try { localStorage.setItem('mafiaSettings', JSON.stringify(S.settings)); } catch (e) { /* ignore */ } }

    async function api(action, body) {
        const res = await fetch(API + '?action=' + action, {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {})
        });
        let data = {};
        try { data = await res.json(); } catch (e) { data = { ok: false, error: 'server', message: 'Ошибка сервера.' }; }
        data.http = res.status;
        return data;
    }

    function toast(text, bad) {
        const box = $('#mf-toasts');
        if (!box) return;
        const n = document.createElement('div');
        n.className = 'mf-toast' + (bad ? ' bad' : '');
        n.textContent = text;
        box.appendChild(n);
        setTimeout(() => n.remove(), 3800);
    }

    /* ---------- page skeleton ---------- */
    function build() {
        if (page) return;
        page = document.createElement('div');
        page.id = 'mafia-page';
        page.className = 'mf-page hidden';
        page.lang = 'ru';
        page.innerHTML = `
            <div class="mf-bg" aria-hidden="true"><div class="mf-moon"></div><div class="mf-fog f1"></div><div class="mf-fog f2"></div>
                <svg class="mf-city" viewBox="0 0 1200 220" preserveAspectRatio="none"><path d="M0 220V150l40-6v-40h30v30l30-10V90l26-14 26 14v54h34v-70h40v70l24-4V110h44v38l30-6v-52l30-24 30 24v52h50v-30h36v30l24-2v-64h46v66l40 4V120h34v40l30-4v-50h42v54l40-8V96l32-18 32 18v60h44v-36h40v40l30-6V100h38v60l34-6v66z"/></svg></div>
            <div class="mf-topbar">
                <button class="mf-iconbtn" data-act="back" id="mf-back" aria-label="Назад">←</button>
                <div class="mf-title">МАФИЯ</div>
                <button class="mf-iconbtn" data-act="settings" aria-label="Настройки">⚙</button>
            </div>
            <div class="mf-net hidden" id="mf-net">Переподключение...</div>
            <main class="mf-screen" id="mf-screen"></main>
            <div class="mf-modal hidden" id="mf-modal"></div>
            <div class="mf-toasts" id="mf-toasts" aria-live="polite"></div>`;
        document.body.appendChild(page);
        page.addEventListener('click', onClick);
        page.addEventListener('input', onInput);
        page.addEventListener('keydown', e => {
            if (e.key === 'Enter' && e.target.id === 'mf-chat-input') { e.preventDefault(); sendChat(); }
            if (e.key === 'Enter' && e.target.id === 'mf-code-input') { e.preventDefault(); doJoinCode(); }
        });
        setInterval(tickTimer, 250);
    }

    /* ---------- open / close ---------- */
    function open(joinCode) {
        build();
        if (!joinCode) joinCode = window.Mafia.pendingAfterLogin();
        S.open = true;
        page.classList.remove('hidden');
        document.body.classList.add('mf-open');
        S.screen = '';
        S.sig = '';
        if (joinCode) S.pendingJoin = String(joinCode).toUpperCase();
        showMenu();
        poll(true);
    }

    function close() {
        S.open = false;
        clearTimeout(S.pt);
        if (page) page.classList.add('hidden');
        document.body.classList.remove('mf-open');
        syncAmbience();
        if (typeof window.onMafiaClosed === 'function') window.onMafiaClosed();
    }

    /* ---------- polling ---------- */
    async function poll(first) {
        if (!S.open) return;
        clearTimeout(S.pt);
        let next = 3000;
        try {
            if (S.view && S.view.code) {
                const r = await api('state');
                setNet(false);
                if (r.http === 401) return showAuth();
                if (r.ok && r.view) { setView(r.view); }
                else if (r.ok && !r.view) { leftRoom(); }
                else if (r.error === 'not_member' || r.error === 'no_room') { leftRoom(r.message); }
                else if (r.message && r.error !== 'server') { toast(r.message, true); }
                next = 1000;
                if (S.view && S.view.phase === 'LOBBY' && Date.now() - S.hubAt > 3000) refreshHub(true);
            } else {
                const h = await refreshHub(false);
                if (h && h.ok && h.room && (first || S.autoResume)) {
                    S.autoResume = false;
                    const r = await api('state');
                    if (r.ok && r.view) { setView(r.view); next = 1000; }
                } else if (h && h.ok && S.pendingJoin) {
                    const code = S.pendingJoin;
                    S.pendingJoin = null;
                    await joinByCode(code);
                    next = 1000;
                }
            }
        } catch (e) {
            setNet(true);
            next = 2000;
        }
        S.pt = setTimeout(poll, next);
    }

    async function refreshHub(quiet) {
        const h = await api('hub');
        S.hubAt = Date.now();
        setNet(false);
        if (h.http === 401) { if (!quiet) showAuth(); return h; }
        if (h.ok) {
            S.hub = h;
            if (S.screen === 'menu') updateMenu();
            if (S.screen === 'lobby') updateInvitePanel();
        }
        return h;
    }

    function setNet(bad) {
        S.netBad = bad;
        const n = $('#mf-net');
        if (n) n.classList.toggle('hidden', !bad);
    }

    function setView(v) {
        S.offset = v.now - Date.now() / 1000;
        S.view = v;
        render();
    }

    function leftRoom(message) {
        S.view = null;
        S.sig = '';
        S.chatSeen = {};
        S.eventSeen = {};
        S.sel = null;
        if (message) toast(message, true);
        showMenu();
        refreshHub(true);
    }

    /* ---------- screens ---------- */
    function showAuth() {
        S.screen = 'auth';
        $('#mf-screen').innerHTML = `<section class="mf-panel mf-auth">
            <h1>МАФИЯ</h1><p class="mf-sub">Город засыпает...</p>
            <p>Чтобы играть в Мафию, войдите в свой аккаунт.</p>
            <button class="mf-btn primary" data-act="login">ВОЙТИ В АККАУНТ</button>
            <button class="mf-btn" data-act="exit">НАЗАД</button></section>`;
    }

    function showMenu() {
        S.screen = 'menu';
        page.classList.remove('night');
        $('#mf-back').textContent = '←';
        $('#mf-screen').innerHTML = `
            <div class="mf-menu">
                <div id="mf-banner"></div>
                <header class="mf-hero"><h1>МАФИЯ</h1><p class="mf-sub">Город засыпает...</p></header>
                <div class="mf-modes">${MODES.map(m => `<button class="mf-mode" data-act="mode" data-mode="${m.id}">
                    <b>${m.title}</b><span>${m.sub}</span></button>`).join('')}</div>
                <div class="mf-actions-row">
                    <button class="mf-btn primary" data-act="quick">БЫСТРАЯ ИГРА</button>
                    <button class="mf-btn" data-act="create">СОЗДАТЬ КОМНАТУ</button>
                    <button class="mf-btn" data-act="joincode">ВОЙТИ ПО КОДУ</button>
                    <button class="mf-btn" data-act="solo">ИГРАТЬ С ИИ</button>
                </div>
                <div class="mf-two">
                    <section class="mf-panel"><h2>ТОП ИГРОКОВ</h2><div id="mf-top"></div></section>
                    <section class="mf-panel"><h2>МОЯ СТАТИСТИКА</h2><div id="mf-stats"></div></section>
                </div>
            </div>`;
        updateMenu();
    }

    function updateMenu() {
        const h = S.hub;
        if (!h) return;
        const banner = $('#mf-banner');
        if (banner) {
            let html = '';
            if (h.room) html += `<div class="mf-notice"><span>Вы в комнате <b>${esc(h.room.code)}</b></span><button class="mf-btn small primary" data-act="resume">ВЕРНУТЬСЯ</button><button class="mf-btn small" data-act="leaveold">ВЫЙТИ</button></div>`;
            (h.invites || []).forEach(i => {
                html += `<div class="mf-notice invite"><span>Вас приглашают в игру Мафия<br><small>от ${esc(i.from)}</small></span>
                    <button class="mf-btn small primary" data-act="accept" data-id="${i.id}">ПРИНЯТЬ</button><button class="mf-btn small" data-act="decline" data-id="${i.id}">ОТКЛОНИТЬ</button></div>`;
            });
            banner.innerHTML = html;
        }
        const top = $('#mf-top');
        if (top) {
            if (!h.top.length) top.innerHTML = '<p class="mf-muted">Пока никто не набрал очков.</p>';
            else top.innerHTML = `<ol class="mf-podium">${h.top.map(t => `<li class="p${t.rank}"><span class="rk">${t.rank}</span><b>${esc(t.name)}</b><em>${t.total.toLocaleString('ru-RU')} очков</em></li>`).join('')}</ol>`;
        }
        const st = $('#mf-stats');
        if (st) {
            const s = h.stats;
            st.innerHTML = `<div class="mf-statgrid">
                <div><b>${s.played}</b><span>Игр сыграно</span></div><div><b>${s.wins}</b><span>Побед</span></div>
                <div><b>${s.win_rate}%</b><span>Процент побед</span></div><div><b>${s.points.toLocaleString('ru-RU')}</b><span>Всего очков</span></div>
                <div><b>${s.mafia_wins}</b><span>Побед за мафию</span></div><div><b>${s.civilian_wins}</b><span>Побед за мирных</span></div>
                <div><b>${s.detective_wins}</b><span>Побед детективом</span></div><div><b>${s.doctor_wins}</b><span>Побед доктором</span></div>
            </div><p class="mf-muted small">Игры с ИИ считаются отдельно: ${s.ai_played} игр, ${s.ai_wins} побед.</p>`;
        }
    }

    /* ---------- dialogs ---------- */
    function modal(html, cls) {
        const m = $('#mf-modal');
        m.className = 'mf-modal ' + (cls || '');
        m.innerHTML = `<div class="mf-dialog">${html}</div>`;
        return m;
    }

    function closeModal() {
        const m = $('#mf-modal');
        if (m) { m.className = 'mf-modal hidden'; m.innerHTML = ''; }
        S.dialog = null;
    }

    function createDialog(modeId) {
        S.dialog = { type: 'create', mode: modeId || 'six', fill: true, pub: true };
        renderCreateDialog();
    }

    function renderCreateDialog() {
        const d = S.dialog;
        const chips = MODES.map(m => `<button class="mf-chip ${d.mode === m.id ? 'on' : ''}" data-act="pickmode" data-mode="${m.id}">${m.title}<small>${m.sub}</small></button>`).join('');
        const duo = d.mode === 'duo';
        modal(`<h2>СОЗДАТЬ КОМНАТУ</h2>
            <div class="mf-chips">${chips}</div>
            <label class="mf-check"><input type="checkbox" id="mf-fill" ${(duo || d.fill) ? 'checked' : ''} ${duo ? 'disabled' : ''}> Заполнить пустые места игроками ИИ</label>
            <label class="mf-check"><input type="checkbox" id="mf-pub" ${d.pub ? 'checked' : ''}> Открытая комната (видна в быстрой игре)</label>
            <p class="mf-muted small">${duo ? 'Дуо: 2 живых игрока и 4 игрока ИИ — всего 6.' : 'Роли: ' + ROLE_SETUP[MODES.find(m => m.id === d.mode).size] + '.'}</p>
            <div class="mf-dlg-btns"><button class="mf-btn primary" data-act="docreate">СОЗДАТЬ КОМНАТУ</button><button class="mf-btn" data-act="closemodal">ОТМЕНА</button></div>`);
    }

    function joinDialog() {
        modal(`<h2>ВОЙТИ ПО КОДУ</h2><p class="mf-muted">Введите код комнаты</p>
            <input id="mf-code-input" class="mf-input code" maxlength="8" autocomplete="off" autocapitalize="characters" placeholder="K7M4Q" inputmode="text">
            <div class="mf-dlg-btns"><button class="mf-btn primary" data-act="dojoin">ВОЙТИ</button><button class="mf-btn" data-act="closemodal">ОТМЕНА</button></div>`);
        setTimeout(() => { const i = $('#mf-code-input'); if (i) i.focus(); }, 50);
    }

    function confirmDialog(text, yesAct, data) {
        const m = modal(`<h2>${esc(text)}</h2><div class="mf-dlg-btns"><button class="mf-btn primary" data-act="${yesAct}" ${data || ''}>ДА</button><button class="mf-btn" data-act="closemodal">ОТМЕНА</button></div>`);
        return m;
    }

    function roleDialog() {
        const v = S.view;
        if (!v || !v.me.role) return;
        const r = ROLES[v.me.role];
        let extra = '';
        if (v.me.role === 'mafia' && v.me.teammates && v.me.teammates.length) {
            extra += `<p class="mf-role-extra">Ваш напарник: <b>${v.me.teammates.map(t => esc(nameOf(t))).join(', ')}</b></p>`;
        }
        if (v.me.role === 'detective' && v.me.checks && v.me.checks.length) {
            extra += '<ul class="mf-dossier">' + v.me.checks.map(c => `<li><b>${esc(nameOf(c.seat))}</b>: ${c.mafia ? 'связан с мафией' : 'не является мафией'}</li>`).join('') + '</ul>';
        }
        modal(`<div class="mf-rolecard ${r.cls}"><div class="mf-roleicon">${r.icon}</div><small>ВАША РОЛЬ</small><h2>${r.name}</h2>
            <p>${r.text}</p>${extra}<p class="mf-muted small">Эта информация видна только вам.</p></div>
            <div class="mf-dlg-btns"><button class="mf-btn primary" data-act="closemodal">ПОНЯТНО</button></div>`, 'role');
        sound.play('role');
    }

    function settingsDialog() {
        modal(`<h2>НАСТРОЙКИ</h2>
            <label class="mf-check"><input type="checkbox" data-set="sound" ${S.settings.sound ? 'checked' : ''}> Звук</label>
            <label class="mf-check"><input type="checkbox" data-set="music" ${S.settings.music ? 'checked' : ''}> Музыка</label>
            <label class="mf-check"><input type="checkbox" data-set="anim" ${S.settings.anim ? 'checked' : ''}> Анимации</label>
            <div class="mf-dlg-btns"><button class="mf-btn primary" data-act="closemodal">ГОТОВО</button></div>`);
    }

    /* ---------- actions from the menu ---------- */
    async function doCreate(body) {
        if (S.busy) return;
        S.busy = true;
        try {
            const r = await api('create', body);
            if (r.http === 401) return showAuth();
            if (!r.ok) return toast(r.message || 'Не удалось создать комнату.', true);
            closeModal();
            setView(r.view);
            poll();
        } finally { S.busy = false; }
    }

    async function joinByCode(code) {
        code = String(code || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (code.length < 4) return toast('Введите код комнаты.', true);
        const r = await api('join', { code });
        if (r.http === 401) return showAuth();
        if (!r.ok) return toast(r.message || 'Не удалось войти.', true);
        closeModal();
        setView(r.view);
    }

    async function doJoinCode() {
        const i = $('#mf-code-input');
        if (i) await joinByCode(i.value);
    }

    async function quickPlay() {
        const r = await api('quick', {});
        if (r.http === 401) return showAuth();
        if (r.ok) { setView(r.view); return; }
        if (r.error === 'no_match') {
            modal(`<h2>${esc(r.message)}</h2><p class="mf-muted">Никто не ждёт в открытых комнатах.</p>
                <div class="mf-dlg-btns"><button class="mf-btn primary" data-act="create">СОЗДАТЬ КОМНАТУ</button><button class="mf-btn" data-act="solo">ИГРАТЬ С ИИ</button><button class="mf-btn" data-act="closemodal">ОТМЕНА</button></div>`);
            return;
        }
        toast(r.message || 'Ошибка.', true);
    }

    /* ---------- click routing ---------- */
    async function onClick(e) {
        const set = e.target.closest('[data-set]');
        if (set && e.target.type === 'checkbox') return;
        const el = e.target.closest('[data-act]');
        if (!el || !page.contains(el)) {
            const card = e.target.closest('.mf-card.selectable');
            if (card) pickTarget(Number(card.dataset.seat));
            return;
        }
        const act = el.dataset.act;
        sound.play('click');
        switch (act) {
            case 'back':
                if (S.view && S.view.code) confirmDialog(S.view.phase === 'LOBBY' || S.view.phase === 'GAME_OVER' ? 'Покинуть комнату?' : 'Покинуть игру? Ваше место займёт ИИ (если он включён) или вы выбудете.', 'leave');
                else close();
                break;
            case 'exit': close(); break;
            case 'login':
                try { sessionStorage.setItem('mafiaPendingJoin', S.pendingJoin || ''); } catch (er) { /* ignore */ }
                close();
                if (typeof window.openAuthScreen === 'function') window.openAuthScreen();
                break;
            case 'settings': settingsDialog(); break;
            case 'closemodal': closeModal(); break;
            case 'mode': createDialog(el.dataset.mode); break;
            case 'create': closeModal(); createDialog(S.dialog ? S.dialog.mode : 'six'); break;
            case 'pickmode': { const d = S.dialog; d.fill = $('#mf-fill') ? $('#mf-fill').checked : d.fill; d.pub = $('#mf-pub') ? $('#mf-pub').checked : d.pub; d.mode = el.dataset.mode; renderCreateDialog(); break; }
            case 'docreate': {
                const d = S.dialog;
                const mode = MODES.find(m => m.id === d.mode);
                const fill = d.mode === 'duo' ? true : $('#mf-fill').checked;
                const pub = $('#mf-pub').checked;
                doCreate(d.mode === 'duo' ? { mode: 'duo', public: pub } : { size: mode.size, fill_ai: fill, public: pub });
                break;
            }
            case 'joincode': joinDialog(); break;
            case 'dojoin': doJoinCode(); break;
            case 'quick': closeModal(); quickPlay(); break;
            case 'solo': closeModal(); doCreate({ mode: 'ai' }); break;
            case 'resume': { const r = await api('state'); if (r.ok && r.view) { setView(r.view); poll(); } else refreshHub(true); break; }
            case 'leaveold': await api('leave'); refreshHub(true); break;
            case 'accept': { const r = await api('answer_invite', { id: Number(el.dataset.id), accept: true }); if (r.ok) { setView(r.view); poll(); } else { toast(r.message || 'Не удалось принять приглашение.', true); refreshHub(true); } break; }
            case 'decline': await api('answer_invite', { id: Number(el.dataset.id), accept: false }); refreshHub(true); break;
            case 'leave': closeModal(); await api('leave'); leftRoom(); break;
            case 'exitgame': closeModal(); await api('leave'); leftRoom(); close(); break;
            case 'ready': roomAction('ready', { ready: !me().ready }); break;
            case 'start': roomAction('start'); break;
            case 'addai': roomAction('add_ai'); break;
            case 'fillai': roomAction('fill_ai'); break;
            case 'removeai': roomAction('remove_ai', { seat: Number(el.dataset.seat) }); break;
            case 'kick': roomAction('kick', { seat: Number(el.dataset.seat) }); break;
            case 'copycode': copyText(S.view.code, 'Код скопирован.'); break;
            case 'copylink': copyText(joinLink(S.view.code), 'Ссылка скопирована.'); break;
            case 'invitepanel': S.invitesOpen = !S.invitesOpen; updateInvitePanel(); break;
            case 'invite': invite(el.dataset.pub, el); break;
            case 'myrole': roleDialog(); break;
            case 'chat': sendChat(); break;
            case 'tab': S.tab = el.dataset.tab; syncTabs(); break;
            case 'confirm': doConfirm(); break;
            case 'yesvote': closeModal(); sendAction('vote', S.sel); break;
            case 'rematch': roomAction('rematch'); break;
            case 'tolobby': await api('leave'); leftRoom(); break;
        }
    }

    function onInput(e) {
        if (e.target.id === 'mf-code-input') e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (e.target.dataset && e.target.dataset.set) {
            S.settings[e.target.dataset.set] = e.target.checked;
            saveSettings();
            page.classList.toggle('mf-noanim', !S.settings.anim);
            syncAmbience();
        }
    }

    function me() { return S.view.seats[S.view.me.seat]; }

    function joinLink(code) {
        return location.origin + location.pathname.replace(/[^/]*$/, '') + '?mafia=' + encodeURIComponent(code);
    }

    async function copyText(text, okMsg) {
        try { await navigator.clipboard.writeText(text); toast(okMsg); }
        catch (e) {
            const t = document.createElement('textarea');
            t.value = text; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); toast(okMsg); } catch (er) { toast(text); }
            t.remove();
        }
    }

    async function roomAction(action, body) {
        const r = await api(action, body);
        if (r.http === 401) return showAuth();
        if (r.ok && r.view) setView(r.view);
        else if (r.error === 'not_member' || r.error === 'no_room') leftRoom(r.message);
        else toast(r.message || 'Ошибка.', true);
    }

    async function sendAction(type, target) {
        if (target === null || target === undefined) return toast('Выберите игрока.', true);
        const r = await api('act', { type, target, pid: S.view.pid });
        if (r.ok && r.view) { S.sel = null; setView(r.view); if (type === 'vote') sound.play('vote'); }
        else if (r.error === 'stale') { S.sel = null; }
        else toast(r.message || 'Ошибка.', true);
    }

    async function sendChat() {
        const input = $('#mf-chat-input');
        if (!input) return;
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        const r = await api('act', { type: 'chat', text, pid: S.view.pid });
        if (r.ok && r.view) setView(r.view);
        else { toast(r.message || 'Сообщение не отправлено.', true); input.value = text; }
    }

    async function invite(pub, btn) {
        const r = await api('invite', { to: pub });
        if (r.ok) { toast('Приглашение отправлено.'); if (btn) { btn.textContent = 'ОТПРАВЛЕНО'; btn.disabled = true; } }
        else toast(r.message || 'Не удалось пригласить.', true);
    }

    /* ---------- render router ---------- */
    function render() {
        const v = S.view;
        if (!v) return;
        const screen = v.phase === 'LOBBY' ? 'lobby' : v.phase === 'GAME_OVER' ? 'result' : 'game';
        if (S.screen !== screen) {
            S.screen = screen;
            S.sig = '';
            S.sel = null;
            closeModalIfStale();
            if (screen === 'lobby') buildLobby();
            else if (screen === 'game') buildGame();
            else buildResult();
        }
        page.classList.toggle('night', v.phase === 'NIGHT' || v.phase === 'ROLE_REVEAL');
        $('#mf-back').textContent = '←';
        if (screen === 'lobby') updateLobby();
        else if (screen === 'game') updateGame();
        else updateResult();
        syncAmbience();
    }

    function closeModalIfStale() {
        if (S.dialog && S.dialog.type === 'create') return;
        const m = $('#mf-modal');
        if (m && !m.classList.contains('hidden') && !m.classList.contains('role')) closeModal();
    }

    /* ---------- chat + journal (shared by lobby and game) ---------- */
    function chatHtml(placeholder) {
        return `<section class="mf-side">
            <div class="mf-tabs"><button class="on" data-act="tab" data-tab="chat" id="mf-tab-chat">ЧАТ</button><button data-act="tab" data-tab="log" id="mf-tab-log">ЖУРНАЛ</button></div>
            <div class="mf-chatlist" id="mf-chatlist"></div>
            <div class="mf-loglist hidden" id="mf-loglist"></div>
            <div class="mf-chatform" id="mf-chatform"><input id="mf-chat-input" class="mf-input" maxlength="200" autocomplete="off" placeholder="${esc(placeholder || 'Сообщение...')}">
                <button class="mf-btn primary" data-act="chat" aria-label="Отправить">➤</button></div></section>`;
    }

    function syncTabs() {
        const chat = $('#mf-chatlist'), log = $('#mf-loglist');
        if (!chat) return;
        chat.classList.toggle('hidden', S.tab !== 'chat');
        log.classList.toggle('hidden', S.tab !== 'log');
        $('#mf-tab-chat').classList.toggle('on', S.tab === 'chat');
        $('#mf-tab-log').classList.toggle('on', S.tab === 'log');
        $('#mf-chatform').classList.toggle('hidden', S.tab !== 'chat');
    }

    function updateChat() {
        const v = S.view;
        const list = $('#mf-chatlist');
        if (!list) return;
        const near = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
        v.chat.forEach(m => {
            if (S.chatSeen[m.id]) return;
            S.chatSeen[m.id] = true;
            const row = document.createElement('div');
            row.className = 'mf-msg ' + m.ch;
            const who = document.createElement('b');
            who.style.color = m.seat === null ? '' : color(m.seat);
            who.textContent = m.seat === null ? 'Город' : nameOf(m.seat);
            row.appendChild(who);
            if (m.ch === 'dead') { const t = document.createElement('i'); t.textContent = ' (наблюдатель)'; row.appendChild(t); }
            if (m.ch === 'mafia') { const t = document.createElement('i'); t.textContent = ' (мафия)'; row.appendChild(t); }
            row.appendChild(document.createTextNode(': '));
            const span = document.createElement('span');
            span.textContent = m.text;         // never innerHTML: chat text is untrusted
            row.appendChild(span);
            list.appendChild(row);
        });
        if (near) list.scrollTop = list.scrollHeight;
        const log = $('#mf-loglist');
        v.events.forEach(ev => {
            if (S.eventSeen[ev.id]) return;
            S.eventSeen[ev.id] = true;
            const row = document.createElement('div');
            row.className = 'mf-logrow ' + ev.type;
            row.textContent = ev.text;
            log.appendChild(row);
            log.scrollTop = log.scrollHeight;
        });
        const input = $('#mf-chat-input');
        if (input) {
            const can = v.me.can.chat;
            input.disabled = !can;
            input.placeholder = can ? (v.me.can.chat_channel === 'dead' ? 'Чат наблюдателей...' : v.me.can.chat_channel === 'mafia' ? 'Тайный чат мафии...' : 'Сообщение...') : (v.phase === 'NIGHT' ? 'Ночью чат закрыт' : 'Чат сейчас недоступен');
        }
        syncTabs();
    }

    /* ---------- lobby ---------- */
    function buildLobby() {
        $('#mf-screen').innerHTML = `<div class="mf-lobby">
            <section class="mf-panel mf-room">
                <div class="mf-roomhead"><div><small>МАФИЯ · КОД КОМНАТЫ</small><div class="mf-code" id="mf-roomcode"></div></div>
                    <div class="mf-copy"><button class="mf-btn small" data-act="copycode">КОПИРОВАТЬ</button><button class="mf-btn small" data-act="copylink">ССЫЛКА</button></div></div>
                <div class="mf-status" id="mf-lstatus"></div>
                <div class="mf-players" id="mf-lplayers"></div>
                <div class="mf-lbtns" id="mf-lbtns"></div>
                <div id="mf-invites"></div>
            </section>
            ${chatHtml('Сообщение в комнату...')}</div>`;
    }

    function updateLobby() {
        const v = S.view;
        const sig = JSON.stringify([v.seats, v.me, v.fill_ai, v.size, v.events.length]);
        $('#mf-roomcode').textContent = v.code;
        if (sig !== S.sig) {
            S.sig = sig;
            const occupied = v.seats.filter(s => !s.empty).length;
            const host = v.me.host;
            const allReady = v.seats.every(s => s.empty || s.ai || s.ready || s.host);
            $('#mf-lstatus').innerHTML = `<span>${occupied} / ${v.size}</span> ${occupied < v.size && !v.fill_ai ? 'Ожидание игроков...' : allReady ? 'Все игроки готовы.' : 'Ожидание готовности...'}
                <small>Роли: ${ROLE_SETUP[v.size]}${v.fill_ai ? ' · места можно заполнить игроками ИИ' : ''}${v.max_humans < v.size ? ' · живых игроков не больше ' + v.max_humans : ''}</small>`;
            $('#mf-lplayers').innerHTML = v.seats.map(s => {
                if (s.empty) return `<div class="mf-prow empty"><span class="av" style="--c:${color(s.seat)}">${s.seat + 1}</span><b>Свободное место</b></div>`;
                const status = s.ai ? 'ИИ' : (!s.conn ? 'Переподключение...' : (s.ready || s.host) ? 'Готов' : 'Не готов');
                const cls = s.ai ? 'ai' : (!s.conn ? 'off' : (s.ready || s.host) ? 'ready' : 'wait');
                let ctl = '';
                if (host && s.ai) ctl = `<button class="mf-x" data-act="removeai" data-seat="${s.seat}" aria-label="Убрать ИИ">✕</button>`;
                if (host && !s.ai && !s.you) ctl = `<button class="mf-x" data-act="kick" data-seat="${s.seat}" aria-label="Исключить">✕</button>`;
                return `<div class="mf-prow ${s.you ? 'you' : ''}"><span class="av" style="--c:${color(s.seat)}">${initial(s.name)}</span>
                    <b>${s.host ? '👑 ' : ''}${s.ai ? '🤖 ' : ''}${esc(s.name)}${s.you ? ' <em>(вы)</em>' : ''}</b><span class="st ${cls}">${status}</span>${ctl}</div>`;
            }).join('');
            const empty = v.size - occupied;
            let btns = '';
            if (host) {
                const problem = (!v.fill_ai && empty > 0) || !allReady;
                btns += `<button class="mf-btn primary big" data-act="start">НАЧАТЬ ИГРУ</button>`;
                if (v.fill_ai && empty > 0) btns += `<button class="mf-btn" data-act="addai">+ ДОБАВИТЬ ИИ</button><button class="mf-btn" data-act="fillai">ЗАПОЛНИТЬ ИИ</button>`;
                if (problem && v.fill_ai === false) btns += '';
            } else {
                btns += `<button class="mf-btn ${me().ready ? '' : 'primary'} big" data-act="ready">${me().ready ? 'НЕ ГОТОВ' : 'ГОТОВ'}</button>`;
            }
            btns += `<button class="mf-btn" data-act="invitepanel">ПРИГЛАСИТЬ ДРУЗЕЙ</button><button class="mf-btn" data-act="leave">ПОКИНУТЬ КОМНАТУ</button>`;
            $('#mf-lbtns').innerHTML = btns;
            updateInvitePanel();
        }
        updateChat();
    }

    function updateInvitePanel() {
        const box = $('#mf-invites');
        if (!box) return;
        if (!S.invitesOpen) { box.innerHTML = ''; return; }
        const online = (S.hub && S.hub.online) || [];
        const rows = online.length ? online.map(o => `<div class="mf-orow"><span class="av" style="--c:#64748b">${initial(o.name)}</span><b>${esc(o.name)}</b>
            <span class="mf-muted small">${o.busy ? 'в комнате' : 'онлайн'}</span><button class="mf-btn small" data-act="invite" data-pub="${esc(o.pub)}">ПРИГЛАСИТЬ</button></div>`).join('')
            : '<p class="mf-muted">Сейчас в разделе «Мафия» больше никого нет. Отправьте другу код или ссылку.</p>';
        box.innerHTML = `<h3>ИГРОКИ ОНЛАЙН</h3>${rows}`;
    }

    /* ---------- game ---------- */
    function buildGame() {
        $('#mf-screen').innerHTML = `<div class="mf-game">
            <div class="mf-hud">
                <div class="mf-hud-l"><span id="mf-day">ДЕНЬ 1</span><b id="mf-phase">НОЧЬ</b></div>
                <div class="mf-timer" id="mf-timer">00:00</div>
                <button class="mf-btn small" data-act="myrole">МОЯ РОЛЬ</button>
            </div>
            <div class="mf-stage">
                <div class="mf-table" id="mf-table"></div>
                <div class="mf-center" id="mf-center"></div>
            </div>
            <div class="mf-action" id="mf-action"></div>
            ${chatHtml('Сообщение...')}
        </div>`;
    }

    function actionInfo(v) {
        /* what the player may pick right now: { verb, prompt, cands, button, type } or null */
        const me_ = v.me;
        if (!me_.alive) return null;
        const alive = v.seats.filter(s => !s.empty && s.alive);
        if (v.phase === 'NIGHT' && me_.night) {
            if (me_.role === 'mafia') {
                const mates = me_.teammates || [];
                return { type: 'mafia', prompt: 'ВЫБЕРИТЕ ЦЕЛЬ', button: 'ПОДТВЕРДИТЬ', done: me_.night.done, choice: me_.night.choice,
                    cands: alive.filter(s => !s.you && !mates.includes(s.seat)).map(s => s.seat) };
            }
            if (me_.role === 'doctor') {
                return { type: 'doctor', prompt: 'КОГО ВЫ ХОТИТЕ СПАСТИ?', button: 'ЗАЩИТИТЬ', done: me_.night.done, choice: me_.night.choice,
                    cands: alive.filter(s => s.seat !== me_.last_protect).map(s => s.seat) };
            }
            if (me_.role === 'detective') {
                return { type: 'detective', prompt: 'КОГО ВЫ ХОТИТЕ ПРОВЕРИТЬ?', button: 'ПРОВЕРИТЬ', done: me_.night.done, choice: me_.night.choice,
                    cands: alive.filter(s => !s.you).map(s => s.seat) };
            }
        }
        if ((v.phase === 'VOTING' || v.phase === 'REVOTE') && v.vote) {
            let cands = alive.filter(s => !s.you).map(s => s.seat);
            if (v.vote.candidates) cands = cands.filter(c => v.vote.candidates.includes(c));
            return { type: 'vote', prompt: 'Кто, по вашему мнению, мафия?', button: 'ГОЛОСОВАТЬ', done: v.vote.voted, choice: v.vote.mine, cands };
        }
        return null;
    }

    function updateGame() {
        const v = S.view;
        const sig = JSON.stringify([v.seats, v.me, v.phase, v.pid, v.vote, v.elim, v.morning, v.day, S.sel]);
        if (v.pid !== S.lastPid) {
            S.lastPid = v.pid;
            S.sel = null;
            onPhaseChange(v);
        }
        if (sig !== S.sig) {
            S.sig = sig;
            $('#mf-day').textContent = (v.phase === 'NIGHT' || v.phase === 'ROLE_REVEAL' ? 'НОЧЬ ' : 'ДЕНЬ ') + Math.max(1, v.day);
            $('#mf-phase').textContent = PHASE_LABEL[v.phase] || v.phase;
            renderTable(v);
            renderCenter(v);
            renderAction(v);
        }
        updateChat();
        tickTimer();
    }

    function onPhaseChange(v) {
        if (v.phase === 'ROLE_REVEAL') roleDialog();
        else {
            const m = $('#mf-modal');
            if (m && m.classList.contains('role')) closeModal();
        }
        const snd = { NIGHT: 'night', MORNING: 'morning', VOTING: 'vote', GAME_OVER: 'victory', ELIMINATION: 'eliminate' }[v.phase];
        if (snd) sound.play(snd);
    }

    function renderTable(v) {
        const n = v.seats.length;
        const info = actionInfo(v);
        const table = $('#mf-table');
        const mates = v.me.teammates || [];
        table.style.setProperty('--n', n);
        table.innerHTML = v.seats.map((s, i) => {
            const ang = (2 * Math.PI * i) / n - Math.PI / 2;
            const left = 50 + 41 * Math.cos(ang), top = 50 + 40 * Math.sin(ang);
            const sel = info && !info.done && info.cands.includes(s.seat);
            const chosen = (info && info.done && info.choice === s.seat) || (S.sel === s.seat);
            const status = !s.alive ? 'ВЫБЫЛ' : (!s.conn ? 'ОТКЛЮЧЁН' : 'ЖИВ');
            const cls = ['mf-card', s.alive ? '' : 'dead', s.you ? 'you' : '', sel ? 'selectable' : '', chosen ? 'chosen' : '', !s.conn ? 'offline' : ''].join(' ');
            const roleChip = (!s.alive && s.role) ? `<em class="role ${ROLES[s.role].cls}">${ROLES[s.role].name}</em>` : '';
            const mate = mates.includes(s.seat) ? '<em class="role mafia">МАФИЯ</em>' : '';
            return `<div class="${cls}" data-seat="${s.seat}" style="--c:${color(s.seat)};--x:${left}%;--y:${top}%">
                <span class="num">${s.seat + 1}</span><span class="av">${initial(s.name)}</span>
                <b>${esc(s.name)}</b>
                <small>${s.ai ? 'ИИ · ' : ''}${!s.conn && s.alive ? 'Переподключение...' : status}</small>${roleChip}${mate}
                ${s.host ? '<i class="crown">👑</i>' : ''}</div>`;
        }).join('');
    }

    function tallyHtml(elim) {
        const max = Math.max(1, ...elim.tally.map(t => t.count));
        return elim.tally.map(t => `<div class="mf-bar"><span>${esc(nameOf(t.seat))}</span><i style="width:${Math.round(100 * t.count / max)}%;background:${color(t.seat)}"></i><b>${t.count}</b></div>`).join('') || '<p class="mf-muted">Голосов нет.</p>';
    }

    function renderCenter(v) {
        const c = $('#mf-center');
        let html = '';
        const me_ = v.me;
        switch (v.phase) {
            case 'ROLE_REVEAL':
                html = '<h2>ИГРА НАЧИНАЕТСЯ</h2><p>Город засыпает...</p>';
                break;
            case 'NIGHT':
                html = '<h2>НОЧЬ</h2><p class="mf-sub">Город засыпает...</p>';
                if (!me_.night || !actionInfo(v)) html += `<p>${me_.alive ? 'Ожидайте наступления утра...' : 'Вы наблюдаете за ночью.'}</p>`;
                html += '<p class="mf-muted small">Ночные действия выполняются...</p>';
                if (me_.night && me_.night.notice) html += `<p class="mf-warn">${esc(me_.night.notice)}</p>`;
                break;
            case 'MORNING': {
                html = '<h2>УТРО</h2><p class="mf-sub">Город просыпается.</p>';
                if (v.morning && v.morning.died !== null && v.morning.died !== undefined) {
                    const d = seatOf(v.morning.died);
                    html += `<p>Этой ночью город потерял игрока...</p><div class="mf-victim" style="--c:${color(d.seat)}"><span class="av">${initial(d.name)}</span><b>${esc(d.name)}</b>${d.role ? `<em class="role ${ROLES[d.role].cls}">${ROLES[d.role].name}</em>` : ''}</div>`;
                } else html += '<p>Этой ночью никто не погиб.</p>';
                if (me_.role === 'detective' && me_.checks && me_.checks.length) {
                    const last = me_.checks[me_.checks.length - 1];
                    html += `<p class="mf-private">🔍 ${esc(nameOf(last.seat))}: ${last.mafia ? 'Этот игрок связан с мафией.' : 'Этот игрок не является мафией.'}</p>`;
                }
                break;
            }
            case 'DISCUSSION':
                html = '<h2>ОБСУЖДЕНИЕ</h2><p>' + (me_.alive ? 'Обсудите, кто может быть мафией.' : 'Вы выбыли. Вы можете писать в чат наблюдателей.') + '</p>';
                break;
            case 'VOTING':
            case 'REVOTE':
                html = `<h2>${v.phase === 'REVOTE' ? 'ПЕРЕГОЛОСОВАНИЕ' : 'ГОЛОСОВАНИЕ'}</h2>`;
                if (v.phase === 'REVOTE' && v.elim) html += `<p class="mf-warn">НИЧЬЯ</p><p>Голосуем только между: ${v.vote && v.vote.candidates ? v.vote.candidates.map(x => esc(nameOf(x))).join(', ') : ''}</p>`;
                else html += '<p>Кто, по вашему мнению, мафия?</p>';
                if (v.vote) html += `<p class="mf-count">ГОЛОСОВАЛИ<br><b>${v.vote.count} / ${v.vote.total}</b></p>`;
                break;
            case 'ELIMINATION': {
                const e = v.elim;
                html = '<h2>РЕЗУЛЬТАТЫ ГОЛОСОВАНИЯ</h2>';
                if (e) {
                    html += tallyHtml(e);
                    if (e.kind === 'vote') {
                        const d = seatOf(e.seat);
                        html += `<p class="mf-sub">Город сделал свой выбор...</p><div class="mf-victim" style="--c:${color(d.seat)}"><span class="av">${initial(d.name)}</span><b>${esc(d.name).toUpperCase()} ПОКИДАЕТ ИГРУ</b>${d.role ? `<em class="role ${ROLES[d.role].cls}">${ROLES[d.role].name}</em>` : ''}</div>`;
                    } else html += `<p class="mf-warn">${e.kind === 'tie' ? 'НИЧЬЯ' : 'НИКТО НЕ ГОЛОСОВАЛ'}</p><p>Никто не покидает игру.</p>`;
                    if (e.votes && e.votes.length) html += '<details class="mf-votes"><summary>Кто за кого</summary>' + e.votes.map(x => `<div>${esc(nameOf(x.from))} → ${esc(nameOf(x.to))}</div>`).join('') + '</details>';
                }
                break;
            }
        }
        c.innerHTML = html;
    }

    function renderAction(v) {
        const box = $('#mf-action');
        const info = actionInfo(v);
        let html = '';
        if (!v.me.alive) html = '<div class="mf-dead-note">ВЫ ВЫБЫЛИ. Вы наблюдаете за игрой и можете писать в чат наблюдателей.</div>';
        else if (info) {
            if (info.done) {
                html = `<div class="mf-done">✔ ${info.type === 'vote' ? 'Ваш голос принят.' : info.type === 'mafia' ? 'Выбор принят. Ожидание напарника...' : 'Выбор принят.'}</div>`;
                if (info.type === 'mafia' && v.me.night && v.me.night.votes) {
                    const mates = v.me.night.votes.filter(x => x.seat !== v.me.seat);
                    if (mates.length) html += mates.map(x => `<div class="mf-muted small">${esc(nameOf(x.seat))} выбрал: ${esc(nameOf(x.target))}</div>`).join('');
                }
            } else {
                const chosen = S.sel !== null && info.cands.includes(S.sel);
                html = `<div class="mf-prompt">${info.prompt}</div>
                    <div class="mf-pick">${chosen ? esc(nameOf(S.sel)) : 'Нажмите на игрока'}</div>
                    <button class="mf-btn primary big" data-act="confirm" ${chosen ? '' : 'disabled'}>${info.button}</button>`;
                if (info.type === 'mafia' && v.me.night && v.me.night.notice) html = `<div class="mf-warn">${esc(v.me.night.notice)}</div>` + html;
                if (info.type === 'mafia' && v.me.night && v.me.night.votes) {
                    html += v.me.night.votes.filter(x => x.seat !== v.me.seat).map(x => `<div class="mf-muted small">${esc(nameOf(x.seat))} выбрал: ${esc(nameOf(x.target))}</div>`).join('');
                }
            }
        } else if (v.phase === 'NIGHT') html = '<div class="mf-idle">Ожидайте наступления утра...</div>';
        if (v.me.role === 'detective' && v.me.checks && v.me.checks.length) {
            html += '<div class="mf-dossier-box"><b>ДОСЬЕ ДЕТЕКТИВА</b>' + v.me.checks.map(c => `<div>Ночь ${c.night}: ${esc(nameOf(c.seat))} — ${c.mafia ? 'связан с мафией' : 'не является мафией'}</div>`).join('') + '</div>';
        }
        box.innerHTML = html;
    }

    function pickTarget(seat) {
        const info = actionInfo(S.view);
        if (!info || info.done || !info.cands.includes(seat)) return;
        S.sel = seat;
        renderTable(S.view);
        renderAction(S.view);
    }

    function doConfirm() {
        const info = actionInfo(S.view);
        if (!info || S.sel === null) return toast('Выберите игрока.', true);
        if (info.type === 'vote') {
            confirmDialog('Вы уверены? Голос за ' + nameOf(S.sel) + ' изменить нельзя.', 'yesvote');
        } else sendAction(info.type, S.sel);
    }

    let lastTick = -1;
    function tickTimer() {
        if (!S.open || !S.view) return;
        const el = $('#mf-timer');
        if (!el) return;
        const v = S.view;
        if (v.phase_end === null || v.phase_end === undefined) { el.textContent = '--:--'; return; }
        const left = v.phase_end - (Date.now() / 1000 + S.offset);
        el.textContent = fmt(left);
        el.classList.toggle('low', left <= 5 && left > 0);
        const whole = Math.ceil(left);
        if (whole <= 5 && whole > 0 && whole !== lastTick) { lastTick = whole; sound.play('tick'); }
    }

    /* ---------- result ---------- */
    function buildResult() {
        $('#mf-screen').innerHTML = `<div class="mf-result"><section class="mf-panel" id="mf-res"></section>${chatHtml('Сообщение...')}</div>`;
    }

    function updateResult() {
        const v = S.view;
        const r = v.result;
        const sig = JSON.stringify(v.result) + v.me.seat;
        if (sig !== S.sig) {
            S.sig = sig;
            const won = r.winner === 'mafia' ? 'МАФИЯ ПОБЕДИЛА' : r.winner === 'civilians' ? 'МИРНЫЕ ЖИТЕЛИ ПОБЕДИЛИ' : 'ИГРА ПРЕРВАНА';
            const mine = v.me.role === 'mafia' ? 'mafia' : 'civilians';
            const good = r.winner === mine;
            $('#mf-res').innerHTML = `<h1>ИГРА ОКОНЧЕНА</h1><div class="mf-win ${r.winner}">${won}</div>
                ${r.winner === 'abandoned' ? '' : `<p class="mf-yourresult ${good ? 'good' : 'bad'}">${good ? 'ПОБЕДА' : 'ПОРАЖЕНИЕ'}</p>`}
                <div class="mf-rolelist">${r.roles.map(x => `<div class="mf-rrow ${ROLES[x.role].cls}" style="--c:${color(x.seat)}"><span class="av">${initial(x.name)}</span>
                    <b>${x.ai ? '🤖 ' : ''}${esc(x.name)}</b><span class="rl">${ROLES[x.role].icon} ${ROLES[x.role].name}</span></div>`).join('')}</div>
                <div class="mf-statgrid three"><div><b>${r.stats.days}</b><span>Дней</span></div><div><b>${r.stats.votes}</b><span>Голосований</span></div><div><b>${r.stats.players}</b><span>Игроков</span></div></div>
                <div class="mf-dlg-btns"><button class="mf-btn primary" data-act="rematch">СЫГРАТЬ ЕЩЁ</button><button class="mf-btn" data-act="tolobby">ВЕРНУТЬСЯ В ЛОББИ</button><button class="mf-btn" data-act="exitgame">ВЫЙТИ</button></div>`;
        }
        updateChat();
    }

    /* ---------- public API ---------- */
    function joinCodeFromUrl() {
        try {
            const q = new URLSearchParams(location.search).get('mafia');
            if (q) return q;
            const m = location.pathname.match(/\/games\/mafia\/join\/([A-Za-z0-9]+)/i) || location.hash.match(/mafia\/join\/([A-Za-z0-9]+)/i);
            if (m) return m[1];
        } catch (e) { /* ignore */ }
        return '';
    }

    /* shows up in the game type picker of the site lobby, with its cover */
    if (window.Games && Games.register) {
        Games.register({ id: 'mafia', name: 'Mafia', cover: 'assets/mafia.png', external: true, run() { open(); } });
    }

    window.Mafia = {
        open, close, sound,
        joinCodeFromUrl,
        pendingAfterLogin() { try { const c = sessionStorage.getItem('mafiaPendingJoin'); sessionStorage.removeItem('mafiaPendingJoin'); return c || ''; } catch (e) { return ''; } },
        isOpen() { return S.open; }
    };
})();
