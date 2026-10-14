// Seera offline service worker (Phase 1). Deliberately small:
//  - build assets (/build/*) are cached on first use (cache-first; hashed names);
//  - the three mobile pages (attendance, mobile site expense entry, Mobile Sync)
//    are network-first with the last good copy as the offline fallback;
//  - nothing else is cached: no other HTML, no JSON, no POST, no private files,
//    no finance screens. Logout and "Clear offline data" purge the page cache.
const VERSION = 'seera-offline-v1';
const ASSET_CACHE = VERSION + '-assets';
const PAGE_CACHE = VERSION + '-pages';
const OFFLINE_PAGES = ['/admin/hr/attendance/mobile', '/admin/site-expenses/mobile/create', '/admin/mobile-sync'];

self.addEventListener('install', event => { event.waitUntil(self.skipWaiting()); });
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) if (!name.startsWith(VERSION)) await caches.delete(name);
        await self.clients.claim();
    })());
});
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'clear-pages') event.waitUntil(caches.delete(PAGE_CACHE));
    if (event.data && event.data.type === 'clear-all') event.waitUntil(Promise.all([caches.delete(PAGE_CACHE), caches.delete(ASSET_CACHE)]));
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/build/')) { event.respondWith(cacheFirst(request)); return; }
    const page = OFFLINE_PAGES.find(p => url.pathname === p || url.pathname === p + '/');
    if (page && request.mode === 'navigate') { event.respondWith(networkFirstPage(request, page)); }
});

async function cacheFirst(request) {
    const cache = await caches.open(ASSET_CACHE);
    const hit = await cache.match(request);
    if (hit) return hit;
    const response = await fetch(request);
    if (response.ok) cache.put(request, response.clone());
    return response;
}

async function networkFirstPage(request, key) {
    const cache = await caches.open(PAGE_CACHE);
    try {
        const response = await fetch(request);
        // Only a signed-in, successful page is kept; redirects to login or errors never replace the last good copy.
        if (response.ok && response.type === 'basic' && !response.redirected) cache.put(key, response.clone());
        return response;
    } catch (error) {
        const cached = await cache.match(key);
        if (cached) {
            const headers = new Headers(cached.headers);
            headers.set('X-Seera-Offline-Cache', '1');
            return new Response(cached.body, { status: 200, headers });
        }
        return new Response('<!doctype html><meta charset="utf-8"><title>Offline</title><p style="font-family:sans-serif;padding:24px">Offline. Open this page once while connected so it can be used without a connection.</p>', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    }
}
