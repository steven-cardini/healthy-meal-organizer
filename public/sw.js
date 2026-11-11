// Online only (docs/architecture.md#pwa): the cache is used only when the network fails.
// Bump the version when a file in PRECACHE changes.
const CACHE = 'menuplan-v10';
const OFFLINE_PAGE = '/offline.html';
const PRECACHE = [OFFLINE_PAGE, '/assets/app.css', '/assets/icons/icon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_PAGE)));
    } else if (request.method === 'GET' && new URL(request.url).origin === self.location.origin) {
        event.respondWith(fetch(request).catch(() => caches.match(request).then((cached) => cached || Response.error())));
    }
});
