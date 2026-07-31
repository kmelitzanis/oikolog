// Oikolog service worker
// Strategy:
//  - Navigations (HTML): network-first, fall back to cached page, then /offline.
//  - Build assets & icons (hashed/static): cache-first.
//  - Everything else (API, POST, events feed): network only — financial data
//    must never be served stale.
// Bumped for the beam-stack icon set: /icons/* and /favicon.ico are served
// cache-first, so installed PWAs would otherwise keep showing the old mark.
const VERSION = 'oikolog-v2';
const STATIC_CACHE = `${VERSION}-static`;
const PAGE_CACHE = `${VERSION}-pages`;

const OFFLINE_URL = '/offline.html';
const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Only handle same-origin GET requests.
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;

    // Never cache dynamic data endpoints.
    if (url.pathname.startsWith('/api/') || url.pathname === '/bills/events') return;

    // Static assets: cache-first (Vite build files are content-hashed).
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/favicon.ico') {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(req, copy));
                }
                return res;
            }))
        );
        return;
    }

    // Page navigations: network-first with offline fallback.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    if (res.ok) {
                        const copy = res.clone();
                        caches.open(PAGE_CACHE).then((cache) => cache.put(req, copy));
                    }
                    return res;
                })
                .catch(() =>
                    caches.match(req).then((hit) => hit || caches.match(OFFLINE_URL))
                )
        );
    }
});
