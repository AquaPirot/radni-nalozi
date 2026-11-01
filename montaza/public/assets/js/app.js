// MONTAŽA — zajednički JS (bez build koraka)
(function () {
    'use strict';

    const MAX_SIDE = 1600;   // duža strana slike posle smanjivanja
    const QUALITY = 0.8;     // JPEG kvalitet

    // CSRF token za fetch() pozive (dodaje se kao X-CSRF-Token zaglavlje)
    const meta = document.querySelector('meta[name="csrf-token"]');
    window.MZ = {
        csrf: meta ? meta.content : '',
        post(url, data) {
            const body = data instanceof FormData ? data : new URLSearchParams(data || {});
            return fetch(url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': this.csrf, 'Accept': 'application/json' },
            });
        },
    };

    // ── Smanjivanje slika u browseru pre slanja (štedi mobilni internet) ──
    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('slika')); };
            img.src = url;
        });
    }

    async function resizeFile(file) {
        if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
        const img = await loadImage(file); // browser već poštuje EXIF orijentaciju
        const scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        if (scale === 1 && file.type === 'image/jpeg' && file.size < 1.5e6) return file;
        const c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * scale);
        c.height = Math.round(img.naturalHeight * scale);
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', QUALITY));
        if (!blob) return file;
        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    }

    // ══ Slanje fajlova jedan po jedan (slaba veza) ══════════════
    // Svaki izabrani fajl se odmah smanji i pošalje (api/upload.php); forma dobija samo id.
    // Neuspelo slanje ostaje označeno — dodir ponovo šalje. Forma ne ide dok se sve ne pošalje.
    const UPLOAD_URL = '/api/upload.php';

    function upItem(list, name, type, file) {
        const el = document.createElement('span');
        el.className = 'up-item busy';
        el.innerHTML = (type === 'pdf' ? '<span class="up-pdf">PDF</span>' : '<img alt="">') +
            '<span class="up-state">0%</span><button type="button" class="up-x" aria-label="Ukloni">×</button>';
        if (type !== 'pdf') el.querySelector('img').src = URL.createObjectURL(file);
        list.appendChild(el);
        el._file = file;
        el._name = name;
        el._type = type;
        return el;
    }

    function sendItem(el) {
        el.classList.remove('err', 'ok');
        el.classList.add('busy');
        const state = el.querySelector('.up-state');
        state.textContent = '0%';
        const prep = el._type === 'pdf' ? Promise.resolve(el._file) : resizeFile(el._file).catch(() => el._file);
        el._job = prep.then((file) => new Promise((resolve) => {
            const fd = new FormData();
            fd.append('file', file, file.name);
            fd.append('type', el._type);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', UPLOAD_URL);
            xhr.setRequestHeader('X-CSRF-Token', window.MZ.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.timeout = 120000;
            xhr.upload.onprogress = (ev) => { if (ev.lengthComputable) state.textContent = Math.round(ev.loaded / ev.total * 100) + '%'; };
            const fail = (msg) => {
                el.classList.remove('busy');
                el.classList.add('err');
                state.textContent = msg || 'Nije poslato — dodirni';
                resolve(false);
                gateAll();
            };
            xhr.onerror = () => fail();
            xhr.ontimeout = () => fail();
            xhr.onload = () => {
                let r = null;
                try { r = JSON.parse(xhr.responseText); } catch (_) {}
                if (xhr.status !== 200 || !r || !r.ok) return fail(r && r.error ? r.error : null);
                el.classList.remove('busy');
                el.classList.add('ok');
                state.textContent = '';
                const hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = el._name + '[]';
                hid.value = r.id;
                el.appendChild(hid);
                resolve(true);
                gateAll();
                saveDraftSoon(el.closest('form'));
            };
            xhr.send(fd);
        }));
        gateAll();
        return el._job;
    }

    document.addEventListener('change', (e) => {
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
        const up = input.closest('[data-uploader]');
        if (!up || !input.files.length) return;
        const list = up.querySelector('.up-list');
        const multiple = up.hasAttribute('data-multiple');
        if (!multiple) list.querySelectorAll('.up-item').forEach((x) => x.remove());
        for (const f of input.files) sendItem(upItem(list, up.dataset.name, up.dataset.type, f));
        input.value = '';
    });

    document.addEventListener('click', (e) => {
        const x = e.target.closest('.up-x');
        if (x) { x.closest('.up-item').remove(); gateAll(); return; }
        const errItem = e.target.closest('.up-item.err');
        if (errItem) sendItem(errItem);
    });

    function uploadsBusy(form) { return form.querySelectorAll('.up-item.busy').length; }
    function uploadsFailed(form) { return form.querySelectorAll('.up-item.err').length; }
    function uploadCount(form, name) { return form.querySelectorAll(`[data-uploader][data-name="${name}"] .up-item.ok`).length; }

    // ══ Slanje forme: potvrda, zaštita od duplog pritiska, dugme koje je kliknuto ══
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.confirm && !form.dataset.confirmed) {
            if (!window.confirm(form.dataset.confirm)) { e.preventDefault(); return; }
            form.dataset.confirmed = '1';
        }
        if (uploadsBusy(form)) {
            e.preventDefault();
            alert('Slike se još šalju — sačekaj da se završe.');
            return;
        }
        if (uploadsFailed(form)) {
            e.preventDefault();
            alert('Neke slike nisu poslate. Dodirni crvenu sliku da pokušaš ponovo ili je ukloni.');
            return;
        }
        if (form.dataset.submitting) { e.preventDefault(); return; }
        // Vrednost kliknutog dugmeta (npr. "Sačuvaj nacrt" / "Pusti u realizaciju") mora da stigne
        if (e.submitter && e.submitter.name) {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = e.submitter.name;
            h.value = e.submitter.value;
            form.appendChild(h);
        }
        form.dataset.submitting = '1';
        const buttons = form.querySelectorAll('button[type=submit]');
        buttons.forEach((b) => { b.disabled = true; });
        clearDraft(form);
        setTimeout(() => { delete form.dataset.submitting; buttons.forEach((b) => { b.disabled = false; }); }, 15000);
    });

    // ══ Zaključano dugme dok nije sve ispunjeno (server svejedno proverava) ══
    function gate(form) {
        const miss = [];
        const min = +(form.dataset.minPhotos || 0);
        if (min) {
            const have = +(form.dataset.existingPhotos || 0) + form.querySelectorAll('.up-item.ok').length;
            if (have < min) miss.push(min === 1 ? 'fotografija' : `još ${min - have} slike`);
        }
        form.querySelectorAll('[data-uploader][data-required]').forEach((u) => {
            if (!u.closest('[hidden]') && getComputedStyle(u).display !== 'none' && !u.querySelector('.up-item.ok')) {
                miss.push(u.querySelector('.up-head span').textContent.replace('*', '').trim().toLowerCase());
            }
        });
        if (form.hasAttribute('data-need-result')) {
            const r = form.querySelector('input[name=result]:checked');
            if (!r) miss.push('ishod');
            const partial = r && r.value !== 'celo';
            const rem = form.querySelector('[data-req-partial]');
            if (partial && rem && !rem.value.trim()) miss.push('šta je ostalo');
            if (r && r.value === 'celo') {
                form.querySelectorAll('[data-req-full]').forEach((c) => { if (!c.checked) miss.push(c.dataset.reqFull); });
            }
        }
        form.querySelectorAll('input[type=checkbox][data-req]').forEach((c) => { if (!c.checked) miss.push(c.dataset.req); });
        if (uploadsBusy(form)) miss.push('slike se šalju…');
        const out = form.querySelector('[data-missing]');
        if (out) out.textContent = miss.length ? 'Fali: ' + miss.join(', ') : '';
        const btn = form.querySelector('button[type=submit]');
        if (btn && !form.dataset.submitting) btn.disabled = miss.length > 0;
    }
    function gateAll() { document.querySelectorAll('form[data-gate]').forEach(gate); }
    document.addEventListener('change', (e) => {
        const form = e.target.closest && e.target.closest('form[data-gate]');
        if (form) setTimeout(() => gate(form), 0);
    });
    document.addEventListener('input', (e) => {
        const form = e.target.closest && e.target.closest('form[data-gate]');
        if (form) gate(form);
    });

    // ══ Rok zadatka: "Bez roka" isključuje datum i vreme ══════
    function syncDeadline(box) {
        const wrap = box.closest('.field');
        wrap.querySelectorAll('[data-deadline] input').forEach((i) => {
            i.disabled = box.checked;
            if (box.checked) i.value = '';
        });
    }
    document.querySelectorAll('input[name=no_deadline]').forEach((b) => {
        syncDeadline(b);
        b.addEventListener('change', () => syncDeadline(b));
    });

    // ══ Sistemi u formi projekta ══════════════════════════════
    let phaseTpl = {};
    try { phaseTpl = JSON.parse((document.getElementById('phase-tpl') || {}).textContent || '{}'); } catch (_) {}

    function syncSystem(row) {
        const sel = row.querySelector('[data-sys-type]');
        const opt = sel && sel.selectedOptions[0];
        const fields = opt && opt.dataset.fields ? opt.dataset.fields.split(',') : [];
        row.querySelectorAll('[data-spec]').forEach((f) => { f.hidden = !fields.includes(f.dataset.spec); });
        const title = row.querySelector('.sys-title');
        if (title) title.textContent = opt && opt.value ? opt.textContent : 'Sistem';
        const du = row.querySelector('[data-done-until]');
        if (du) {
            const cur = du.value !== '0' ? du.value : du.dataset.value;
            du.innerHTML = '<option value="0">ništa</option>';
            (phaseTpl[sel.value] || []).forEach(([sort, name]) => {
                const o = document.createElement('option');
                o.value = sort;
                o.textContent = name;
                if (String(sort) === String(cur)) o.selected = true;
                du.appendChild(o);
            });
        }
    }
    document.querySelectorAll('[data-sys]').forEach(syncSystem);
    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-sys-type]')) syncSystem(e.target.closest('[data-sys]'));
    });
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sys-add]')) {
            const list = document.querySelector('[data-sys-list]');
            const tpl = document.getElementById('sys-template');
            const i = 'n' + Date.now();
            const wrap = document.createElement('div');
            wrap.innerHTML = tpl.innerHTML.replaceAll('__i__', i);
            const row = wrap.firstElementChild;
            list.appendChild(row);
            syncSystem(row);
            row.querySelector('select').focus();
        }
        const rm = e.target.closest('[data-sys-remove]');
        if (rm) {
            const row = rm.closest('[data-sys]');
            const id = row.querySelector('input[name$="[id]"]');
            if (id && +id.value) {
                row.querySelector('[data-sys-delete]').value = '1';
                row.hidden = true;
            } else {
                row.remove();
            }
        }
    });
    if (document.querySelector('[data-sys-list]') && !document.querySelector('[data-sys]') && document.querySelector('#wt-novi:checked')) {
        const add = document.querySelector('[data-sys-add]');
        if (add) add.click();
    }

    // ══ Zastoj: predlog ko rešava po razlogu ══════════════════
    document.addEventListener('change', (e) => {
        const r = e.target.closest('[data-stoppage] input[name=reason]');
        if (!r) return;
        const sel = r.form.querySelector('select[name=owner_id]');
        if (sel && !sel.dataset.touched && +r.dataset.owner) sel.value = r.dataset.owner;
    });
    document.addEventListener('change', (e) => { if (e.target.matches('[data-stoppage] select[name=owner_id]')) e.target.dataset.touched = '1'; });

    // ══ Automatsko čuvanje nacrta forme na telefonu ═══════════
    // Tekst i izbori se čuvaju dok se kuca; posle uspešnog slanja brišu se.
    const draftKey = (form) => 'mz_draft_' + form.dataset.autosave;
    const draftTimers = new WeakMap();
    function serialize(form) {
        const data = {};
        for (const el of form.elements) {
            if (!el.name || el.type === 'hidden' || el.type === 'file' || el.name.startsWith('_')) continue;
            if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) continue;
            (data[el.name] ||= []).push(el.value);
        }
        return data;
    }
    function saveDraftSoon(form) {
        if (!form || !form.dataset.autosave) return;
        clearTimeout(draftTimers.get(form));
        draftTimers.set(form, setTimeout(() => {
            try {
                localStorage.setItem(draftKey(form), JSON.stringify({ t: Date.now(), d: serialize(form) }));
                const st = form.querySelector('[data-autosave-status]');
                if (st) st.textContent = 'Sačuvano na telefonu ' + new Date().toLocaleTimeString('sr-RS', { hour: '2-digit', minute: '2-digit' });
            } catch (_) {}
        }, 600));
    }
    function clearDraft(form) { try { if (form.dataset.autosave) localStorage.removeItem(draftKey(form)); } catch (_) {} }
    document.querySelectorAll('form[data-autosave]').forEach((form) => {
        let saved = null;
        try { saved = JSON.parse(localStorage.getItem(draftKey(form)) || 'null'); } catch (_) {}
        if (saved && saved.d && Date.now() - saved.t < 7 * 864e5 && !document.querySelector('.alert.warn')) {
            const st = form.querySelector('[data-autosave-status]');
            const bar = document.createElement('div');
            bar.className = 'alert';
            bar.innerHTML = 'Postoji nesačuvan unos sa ovog telefona. <button type="button" class="link accent">Vrati ga</button>';
            form.prepend(bar);
            bar.querySelector('button').addEventListener('click', () => {
                // dodaj redove sistema koliko treba, pa popuni vrednosti
                const sysNames = Object.keys(saved.d).filter((n) => /^systems\[/.test(n));
                const keys = [...new Set(sysNames.map((n) => n.match(/^systems\[([^\]]+)\]/)[1]))];
                keys.forEach((k) => {
                    if (!form.querySelector(`[name^="systems[${k}]"]`)) {
                        const tpl = document.getElementById('sys-template');
                        if (!tpl) return;
                        const wrap = document.createElement('div');
                        wrap.innerHTML = tpl.innerHTML.replaceAll('__i__', k);
                        form.querySelector('[data-sys-list]').appendChild(wrap.firstElementChild);
                    }
                });
                for (const [name, vals] of Object.entries(saved.d)) {
                    const els = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
                    els.forEach((el, i) => {
                        if (el.type === 'radio' || el.type === 'checkbox') el.checked = vals.includes(el.value);
                        else if (vals[i] !== undefined) el.value = vals[i];
                    });
                }
                form.querySelectorAll('[data-sys]').forEach(syncSystem);
                bar.remove();
                if (st) st.textContent = 'Unos je vraćen.';
            });
        }
        form.addEventListener('input', () => saveDraftSoon(form));
        form.addEventListener('change', () => saveDraftSoon(form));
    });

    document.addEventListener('DOMContentLoaded', gateAll);
    if (document.readyState !== 'loading') gateAll();

    // ── PWA: service worker (ikonica na početnom ekranu, keš statike, obaveštenja) ──
    const secure = location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname);
    if ('serviceWorker' in navigator && secure) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    // ── Instalacija kao aplikacija (Android/Chrome: pravi WebAPK, ne prečica) ──
    const installBanner = document.querySelector('[data-install-banner]');
    const INSTALL_DISMISS = 'mz_install_dismissed';
    let installEvent = null;
    const installDismissed = () => { try { return localStorage.getItem(INSTALL_DISMISS) === '1'; } catch (_) { return false; } };
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();             // naše dugme umesto Chrome-ovog trenutnog oblačića
        installEvent = e;
        if (installBanner && !installDismissed()) installBanner.hidden = false;
    });
    window.addEventListener('appinstalled', () => {
        installEvent = null;
        if (installBanner) installBanner.hidden = true;
    });
    if (installBanner) {
        installBanner.querySelector('[data-install-go]').addEventListener('click', async () => {
            if (installEvent) {
                installEvent.prompt();
                await installEvent.userChoice.catch(() => null);
                installEvent = null;
                installBanner.hidden = true;
            } else {
                alert('Chrome: meni ⋮ → „Instaliraj aplikaciju".\niPhone (Safari): dugme Deli (□↑) → „Add to Home Screen".');
            }
        });
        installBanner.querySelector('[data-install-dismiss]').addEventListener('click', () => {
            try { localStorage.setItem(INSTALL_DISMISS, '1'); } catch (_) {}
            installBanner.hidden = true;
        });
        // iPhone nema dugme za instalaciju — prikaži uputstvo dok aplikacija nije dodata
        const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
        const inApp = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        if (ios && !inApp && !installDismissed()) {
            installBanner.querySelector('[data-install-text]').textContent =
                'Safari: dugme Deli (□↑) → „Add to Home Screen". Posle otvaraj Montažu sa ikonice.';
            installBanner.querySelector('[data-install-go]').hidden = true;
            installBanner.hidden = false;
        }
    }

    // ── Obaveštenja iz aplikacije (Web Push) ──
    const vapidMeta = document.querySelector('meta[name="vapid-key"]');
    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const pushOK = secure && vapidMeta && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const iosNeedsInstall = isIOS && !standalone;
    const bell = document.querySelector('[data-push-toggle]');
    const banner = document.querySelector('[data-push-banner]');
    const DISMISS = 'mz_push_dismissed';

    function urlB64ToUint8(b64) {
        const s = atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(s, (c) => c.charCodeAt(0));
    }
    function dismissed() { try { return localStorage.getItem(DISMISS) === '1'; } catch (_) { return false; } }

    async function currentSub() {
        if (!pushOK) return null;
        const reg = await navigator.serviceWorker.ready;
        return reg.pushManager.getSubscription();
    }

    async function refreshPushUI() {
        if (!bell) return;
        if (iosNeedsInstall) {
            bell.hidden = false;
            bell.classList.remove('on');
            if (banner && !dismissed() && (!installBanner || installBanner.hidden)) {
                banner.querySelector('[data-push-banner-text]').textContent =
                    'Na iPhone-u: dugme Deli → „Add to Home Screen", pa otvori Montažu sa ikonice i uključi obaveštenja.';
                banner.querySelector('[data-push-enable]').hidden = true;
                banner.hidden = false;
            }
            return;
        }
        if (!pushOK) return;
        const sub = await currentSub();
        const on = !!sub && Notification.permission === 'granted';
        bell.hidden = false;
        bell.classList.toggle('on', on);
        bell.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (banner) banner.hidden = on || dismissed() || Notification.permission === 'denied';
    }

    async function enablePush() {
        if (iosNeedsInstall) {
            alert('Na iPhone-u obaveštenja rade samo iz instalirane aplikacije:\n\n1. dugme Deli (□↑)\n2. „Add to Home Screen"\n3. otvori Montažu sa početnog ekrana i dodirni zvonce.');
            return;
        }
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
            alert('Obaveštenja su blokirana za ovaj sajt.\nUključi ih u podešavanjima browsera (katanac pored adrese → Obaveštenja → Dozvoli).');
            return refreshPushUI();
        }
        try {
            const reg = await navigator.serviceWorker.ready;
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8(vapidMeta.content) });
            }
            const fd = new FormData();
            fd.append('action', 'subscribe');
            fd.append('subscription', JSON.stringify(sub));
            const res = await window.MZ.post('/api/push.php', fd);
            if (!res.ok) throw new Error('server');
        } catch (e) {
            alert('Uključivanje obaveštenja nije uspelo. Proveri internet i pokušaj ponovo.');
        }
        refreshPushUI();
    }

    async function disablePush() {
        if (!confirm('Isključiti obaveštenja na ovom telefonu?')) return;
        const sub = await currentSub();
        if (sub) {
            const fd = new FormData();
            fd.append('action', 'unsubscribe');
            fd.append('endpoint', sub.endpoint);
            await window.MZ.post('/api/push.php', fd).catch(() => {});
            await sub.unsubscribe().catch(() => {});
        }
        refreshPushUI();
    }

    if (bell) {
        bell.addEventListener('click', () => (bell.classList.contains('on') ? disablePush() : enablePush()));
    }
    if (banner) {
        banner.querySelector('[data-push-enable]').addEventListener('click', enablePush);
        banner.querySelector('[data-push-dismiss]').addEventListener('click', () => {
            try { localStorage.setItem(DISMISS, '1'); } catch (_) {}
            banner.hidden = true;
        });
    }
    refreshPushUI();

    // ── Prikaži/sakrij (npr. pretraga) ──
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-toggle]');
        if (!btn) return;
        const el = document.querySelector(btn.dataset.toggle);
        if (!el) return;
        el.hidden = !el.hidden;
        if (!el.hidden) {
            const inp = el.querySelector('input:not([type=hidden])');
            if (inp) inp.focus();
        }
    });
})();
