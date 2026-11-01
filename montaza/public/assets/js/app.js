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

    const pending = new WeakMap(); // input → Promise dok traje smanjivanje

    async function handleResize(input) {
        if (typeof DataTransfer === 'undefined' || !input.files.length) return;
        try {
            const dt = new DataTransfer();
            for (const f of input.files) dt.items.add(await resizeFile(f));
            input.files = dt.files;
        } catch (_) { /* server ionako smanjuje i proverava */ }
    }

    function showPreview(input) {
        const zone = input.closest('[data-preview]');
        if (!zone) return;
        const img = zone.querySelector('.dz-img');
        const file = input.files[0];
        if (!img) return;
        if (img.src) URL.revokeObjectURL(img.src);
        if (file) {
            img.src = URL.createObjectURL(file);
            img.hidden = false;
            zone.classList.add('has-img');
        } else {
            img.hidden = true;
            zone.classList.remove('has-img');
        }
    }

    function showFilename(input) {
        const box = input.closest('[data-filename]');
        if (!box) return;
        const label = box.querySelector('span');
        if (!box.dataset.label) box.dataset.label = label.textContent;
        const file = input.files[0];
        label.textContent = file ? file.name : box.dataset.label;
        box.classList.toggle('has-file', !!file);
    }

    document.addEventListener('change', (e) => {
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
        const job = (input.hasAttribute('data-resize') ? handleResize(input) : Promise.resolve()).then(() => {
            showPreview(input);
            showFilename(input);
            if (input.hasAttribute('data-add-photos')) addPhotos(input);
            const g = input.closest('form[data-gate]');
            if (g) gate(g);
            const form = input.closest('form[data-autosubmit]');
            if (form && input.files.length) form.requestSubmit ? form.requestSubmit() : form.submit();
        });
        pending.set(input, job);
    });

    // ── Slanje forme ──
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (form.dataset.confirm && !form.dataset.confirmed) {
            if (!window.confirm(form.dataset.confirm)) { e.preventDefault(); return; }
            form.dataset.confirmed = '1';
        }
        if (form.dataset.submitting) { e.preventDefault(); return; }

        form.dataset.submitting = '1';
        const buttons = form.querySelectorAll('button[type=submit]');
        buttons.forEach((b) => { b.disabled = true; });
        setTimeout(() => {
            delete form.dataset.submitting;
            buttons.forEach((b) => { b.disabled = false; });
        }, 15000);

        // Ako se slika još smanjuje, sačekaj pa pošalji (bez ponovnog submit događaja)
        const jobs = [...form.querySelectorAll('input[type=file][data-resize]')].map((i) => pending.get(i)).filter(Boolean);
        if (jobs.length) {
            e.preventDefault();
            await Promise.all(jobs);
            HTMLFormElement.prototype.submit.call(form);
        }
    });

    // ── Dodavanje više slika: svaki izbor ostaje u svom inputu, "+" se pravi ponovo ──
    function addPhotos(input) {
        const tile = input.closest('.thumb.add');
        if (!tile || !input.files.length) return;
        for (const f of input.files) {
            const t = document.createElement('span');
            t.className = 'thumb new';
            const img = document.createElement('img');
            img.alt = '';
            img.src = URL.createObjectURL(f);
            t.appendChild(img);
            tile.before(t);
        }
        // input ostaje u formi (sakriven), a pravi se novo "+" polje
        const fresh = tile.cloneNode(true);
        const newInput = fresh.querySelector('input');
        newInput.value = '';
        tile.classList.remove('add');
        tile.hidden = true;
        tile.after(fresh);
        gateAll();
    }

    // ── Zaključano dugme dok sve nije ispunjeno (server svejedno proverava) ──
    function gate(form) {
        const miss = [];
        const min = +(form.dataset.minPhotos || 0);
        let photos = +(form.dataset.existingPhotos || 0);
        form.querySelectorAll('input[type=file][data-count]').forEach((i) => { photos += i.files.length; });
        const posle = form.querySelector('input[name=posle]');
        const needPosle = form.dataset.needPosle === '1';
        if (needPosle && !(posle && posle.files.length)) miss.push('slika POSLE');
        if (photos < min) miss.push(min === 1 ? 'fotografija' : `još ${min - photos} ${min - photos === 1 ? 'slika' : 'slike'}`);
        form.querySelectorAll('input[type=checkbox][data-req]').forEach((c) => { if (!c.checked) miss.push(c.dataset.req); });
        const out = form.querySelector('[data-missing]');
        if (out) out.textContent = miss.length ? 'Fali: ' + miss.join(', ') : '';
        form.querySelectorAll('[data-show-if-missing="posle"]').forEach((el) => {
            el.hidden = !(needPosle && !(posle && posle.files.length));
        });
        const btn = form.querySelector('button[type=submit]');
        if (btn && !form.dataset.submitting) btn.disabled = miss.length > 0;
    }
    function gateAll() { document.querySelectorAll('form[data-gate]').forEach(gate); }
    document.addEventListener('change', (e) => {
        const form = e.target.closest && e.target.closest('form[data-gate]');
        if (form) setTimeout(() => gate(form), 0);
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
