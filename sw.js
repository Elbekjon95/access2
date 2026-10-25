const CACHE_NAME = 'access-pwa-v1.6.0';
const ASSETS_TO_CACHE = [
  '/',
  '/manifest.json',
  '/img/icons/icon-192.png',
  '/img/icons/icon-512.png',
  '/img/logo_hologram.png'
];

// Install
self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch(() => {});
    })
  );
});

// Activate
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch (Network-first, skriptlar va API larni to'g'ridan-to'g'ri tarmoqdan olish)
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // Skriptlar, API so'rovlar va dinamik ma'lumotlarga Service Worker aralashmaydi
  if (
    url.pathname.endsWith('.js') ||
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/data/') ||
    url.pathname.includes('.php')
  ) {
    return;
  }

  event.respondWith(
    fetch(event.request).catch(async () => {
      const cached = await caches.match(event.request);
      if (cached) return cached;
      return new Response('Network error', { status: 408, headers: { 'Content-Type': 'text/plain' } });
    })
  );
});
