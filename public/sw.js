const CACHE_NAME = 'mayoka-pos-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/manifest.json',
  '/favicon.ico',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Hanya intercept GET request (abaikan API POST/PUT)
  if (event.request.method !== 'GET') return;
  
  // Abaikan request ke pusher/websocket atau external
  if (event.request.url.includes('/pusher/') || event.request.url.includes('socket.io') || !event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Network First, fallback to Cache strategy untuk assets dan API ringan
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // Cache assets/api GET yang berhasil
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});
