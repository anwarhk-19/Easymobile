/* Bhatkal Mobile Hub service worker: offline app shell + cache-first static assets.
   API calls are always network-only (never cache personal data or stale stock).
   All paths are relative to the worker's scope, so it works at a domain root or in a sub-folder. */
const VERSION = 'bmh-v2';
const BASE = new URL(self.registration.scope).pathname.replace(/\/$/, ''); // "" or "/easymobile"
const SHELL = [`${BASE}/`, `${BASE}/manifest.webmanifest`, `${BASE}/icons/icon.svg`, `${BASE}/icons/icon-192.png`];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    const path = url.pathname.startsWith(BASE) ? url.pathname.slice(BASE.length) : url.pathname;
    if (e.request.method !== 'GET' || url.origin !== location.origin || path.startsWith('/api/') || path.startsWith('/storage/')) return;

    if (path.startsWith('/build/') || path.startsWith('/icons/')) {
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
            caches.open(VERSION).then((c) => c.put(`${BASE}/`, copy));
            return res;
        }).catch(() => caches.match(`${BASE}/`)));
    }
});
