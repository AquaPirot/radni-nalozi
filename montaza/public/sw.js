// MONTAŽA — service worker: samo ikonica + keš statike (offline rad nije cilj v1).
// Stranice uvek idu na mrežu (podaci moraju biti sveži); bez mreže → offline.html.
const CACHE = 'montaza-v5';
// Samo ono što treba stranici „nema veze". CSS/JS stranice dolaze sa ?v=… (verzija fajla),
// pa se u keš traže po PUNOJ adresi — nova verzija nikad ne dobije stari fajl.
const STATIC = [
  '/offline.html',
  '/assets/css/montaza.css',
  '/assets/icons/icon-192.png',
  '/assets/icons/badge-72.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;            // fontovi itd. — browser keš
  if (url.pathname.startsWith('/file.php') || url.pathname.startsWith('/api/')) return; // privatno, nikad u keš

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }
  if (url.pathname.startsWith('/assets/')) {
    // Mreža prvo; keš samo kad nema veze. Tačna adresa (sa ?v=), nikad „slična".
    e.respondWith(
      fetch(req)
        .then((res) => {
          if (res.ok && url.search) {
            const copy = res.clone();
            caches.open(CACHE).then(async (c) => {
              for (const old of await c.keys()) {
                const u = new URL(old.url);
                if (u.pathname === url.pathname && u.search && u.search !== url.search) c.delete(old);
              }
              c.put(req, copy);
            });
          }
          return res;
        })
        .catch(() => caches.match(req))
    );
  }
});

// ── Obaveštenja (Web Push) ──
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = { body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'AG Montaža', {
    body: d.body || '',
    icon: '/assets/icons/icon-192.png',
    badge: '/assets/icons/badge-72.png',
    tag: d.tag || undefined,
    renotify: !!d.tag,
    data: { url: d.url || '/' },
    vibrate: [120, 60, 120],
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = new URL((e.notification.data && e.notification.data.url) || '/', self.location.origin).href;
  e.waitUntil((async () => {
    const wins = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const w of wins) {
      if (new URL(w.url).origin === self.location.origin) {
        await w.focus();
        return w.navigate(target).catch(() => clients.openWindow(target));
      }
    }
    return clients.openWindow(target);
  })());
});
