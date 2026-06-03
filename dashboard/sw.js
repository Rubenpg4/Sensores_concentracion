// Service Worker — Dashboard de Concentración
// Estrategia:
//   • App shell (HTML, CSS, JS, iconos, manifest) → Cache-First (precache en install)
//   • CDN externas (jQuery, Flot, Google Fonts)   → Stale-While-Revalidate (runtime)
//   • API PHP (api_realtime, analytics)            → Network-Only (datos en vivo)

const CACHE_NAME = 'concentracion-v5';

// Solo cacheamos assets estáticos que no cambian (iconos y manifest)
const APP_SHELL = [
    './manifest.json',
    './icons/icon-192.png',
    './icons/icon-512.png',
    './icons/icon-maskable-512.png',
    './icons/apple-touch-icon.png',
];

// Orígenes cuyas respuestas se cachean en runtime (stale-while-revalidate)
const CDN_ORIGINS = [
    'https://code.jquery.com',
    'https://cdnjs.cloudflare.com',
    'https://fonts.googleapis.com',
    'https://fonts.gstatic.com',
];

// ─── Install: precache del app shell ──────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL))
    );
    self.skipWaiting();
});

// ─── Activate: limpiar cachés obsoletas ───────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k !== CACHE_NAME)
                    .map(k => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

// ─── Fetch: enrutamiento por estrategia ───────────────────────────────────────
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // 1. HTML, CSS, JS y API PHP → Network-Only (nunca cachear)
    if (
        url.pathname.includes('api_realtime.php') ||
        url.pathname.includes('analytics.php') ||
        url.pathname.endsWith('.html') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.search.startsWith('?v=')
    ) {
        return;
    }

    // 2. CDN externa → Stale-While-Revalidate
    if (CDN_ORIGINS.some(origin => event.request.url.startsWith(origin))) {
        event.respondWith(staleWhileRevalidate(event.request));
        return;
    }

    // 3. App shell y assets locales → Cache-First
    event.respondWith(cacheFirst(event.request));
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        // Sin red y sin caché: devolver respuesta vacía para no romper la UI
        return new Response('', { status: 503, statusText: 'Sin conexión' });
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request).then(response => {
        if (response.ok) cache.put(request, response.clone());
        return response;
    }).catch(() => cached); // Si falla la red, ya tenemos la cacheada

    return cached || fetchPromise;
}
