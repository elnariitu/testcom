/* Client for lobby.php: who is online, matchmaking rooms, invitations and in-game events (plain HTTP polling).
   One tab = one player: the client id lives in sessionStorage. */
(function () {
    'use strict';

    const KEY = 'testcomClientId';
    const hex = n => Array.from({ length: n }, () => Math.floor(Math.random() * 16).toString(16)).join('');

    function readId() {
        try {
            let id = sessionStorage.getItem(KEY);
            if (!/^[a-f0-9]{16,40}$/.test(id || '')) {
                id = hex(24);
                sessionStorage.setItem(KEY, id);
            }
            return id;
        } catch (e) {
            return Net.fallbackId || (Net.fallbackId = hex(24));
        }
    }

    const Net = {
        available: null,          // null = unknown yet, true/false after the first answer
        online: [],               // other players in the lobby: { pub, name, state }
        invites: [],              // pending invitations for me
        room: null,               // my room (party / search / play), or null
        offset: 0,                // server clock minus local clock, seconds
        listeners: [],
        timer: null,
        busy: false,
        lastEventId: 0,
        chain: Promise.resolve(),

        id() { return readId(); },
        nick() { return (typeof getSavedNickname === 'function' && getSavedNickname()) || 'Guest'; },
        serverNow() { return Date.now() / 1000 + Net.offset; },

        async call(action, body) {
            try {
                const res = await fetch('lobby.php?action=' + action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.assign({ client: readId(), name: Net.nick() }, body || {}))
                });
                const data = await res.json();
                if (data && data.now) Net.offset = data.now - Date.now() / 1000;
                Net.available = res.ok && data && data.error !== 'unavailable';
                return data;
            } catch (e) {
                Net.available = false;
                return null;
            }
        },

        on(fn) { Net.listeners.push(fn); },
        emitChange() { Net.listeners.forEach(fn => { try { fn(Net); } catch (e) {} }); },

        async sync() {
            if (Net.busy) return;
            Net.busy = true;
            const data = await Net.call('sync');
            Net.busy = false;
            if (data && data.ok) {
                Net.online = data.online || [];
                Net.invites = data.invites || [];
                Net.room = data.room || null;
            } else if (!data || data.error === 'unavailable') {
                Net.online = [];
                Net.invites = [];
            }
            Net.emitChange();
        },

        /* Poll while the lobby is open; faster while a room exists */
        watch(on) {
            clearInterval(Net.timer);
            Net.timer = null;
            if (!on) return;
            Net.sync();
            const tick = () => {
                Net.sync();
                Net.timer = setTimeout(tick, Net.room ? 1000 : 2500);
            };
            Net.timer = setTimeout(tick, 1000);
        },

        /* Events are sent in order, one request at a time */
        emit(kind, body) {
            Net.chain = Net.chain.then(() => Net.call('emit', { kind, body })).catch(() => {});
            return Net.chain;
        },

        async events() {
            const data = await Net.call('events', { after: Net.lastEventId });
            if (!data || !data.ok) return [];
            const list = data.events || [];
            list.forEach(e => { Net.lastEventId = Math.max(Net.lastEventId, e.id); });
            return list;
        },

        resetEvents() { Net.lastEventId = 0; }
    };

    window.Net = Net;
})();
