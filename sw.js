// ACCSESS PWA Service Worker - Cache cleaner & self-unregister
self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(keys.map((key) => caches.delete(key)));
    }).then(() => {
      return self.registration.unregister();
    }).then(() => {
      return self.clients.claim();
    })
  );
});

// Hech qanday fetch listener yo'q - barcha so'rovlar to'g'ridan-to'g'ri tarmoq orqali to'siqsiz o'tadi
