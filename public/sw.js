/* Bhatkal Mobile Hub service worker: offline app shell + cache-first static assets.
   API calls are always network-only (never cache personal data or stale stock). */
const VERSION = 'bmh-v1';
const SHELL = ['/', '/manifest.webmanifest', '/icons/icon.svg', '/icons/icon-192.png'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api/') || url.pathname.startsWith('/storage/')) return;

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
            const copy = res.clone();
            caches.open(VERSION).then((c) => c.put(e.request, copy));
            return res;
        })));
        return;
    }
    if (e.request.mode === 'navigate') {
        e.respondWith(fetch(e.request).then((res) => {
            const copy = res.clone();
            caches.open(VERSION).then((c) => c.put('/', copy));
            return res;
        }).catch(() => caches.match('/')));
    }
});
