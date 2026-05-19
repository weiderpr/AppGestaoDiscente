const CACHE_NAME = 'vertice-mobile-v2';
const ASSETS_TO_CACHE = [
  '/mobile/index.php',
  '/assets/css/variables.css',
  '/assets/js/components/Toast.js',
  '/assets/js/components/Modal.js',
  '/assets/js/components/Loading.js',
  '/assets/js/network_monitor.js',
  '/assets/images/icon-192.png',
  '/assets/images/icon-512.png',
  '/assets/images/apple-touch-icon.png'
];

// Install Event - Caching basic resources
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // UseaddAll with catch to prevent install failure if some assets fail to load initially
      return cache.addAll(ASSETS_TO_CACHE).catch(err => {
        console.warn('PWA: Some assets failed to pre-cache', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate Event - Clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Network first fallback to cache
self.addEventListener('fetch', (event) => {
  // Only handle HTTP/HTTPS (ignore chrome-extension, etc.)
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Network-first strategy for dynamic pages/scripts, falling back to cache
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // If valid response, clone and cache it for static assets
        if (response && response.status === 200 && response.type === 'basic') {
          const url = new URL(event.request.url);
          // Cache CSS, JS and images dynamically
          if (url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff2)$/)) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, responseToCache);
            });
          }
        }
        return response;
      })
      .catch(() => {
        // Offline: try caching
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If a page fetch fails and is navigation, we can redirect or show offline message
          if (event.request.mode === 'navigate') {
            return caches.match('/mobile/index.php');
          }
        });
      })
  );
});
