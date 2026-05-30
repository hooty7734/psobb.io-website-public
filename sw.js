/**
 * PSOBB Player Portal PWA Service Worker
 * Provides fast caching for static assets and handles basic offline fallbacks.
 */

const CACHE_NAME = 'psobb-portal-cache-v1';
const ASSETS_TO_CACHE = [
  '/login.php',
  '/css/style.css',
  '/css/character_viewer.css',
  '/css/missions.css',
  '/js/main.js',
  '/js/character_viewer.js',
  '/js/character_3d_viewer.js',
  '/img/favicon.svg',
  '/img/steam_icon.png',
  '/img/scanlines.png'
];

// Install Event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Caching app shell and static assets');
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keyList) => {
      return Promise.all(
        keyList.map((key) => {
          if (key !== CACHE_NAME) {
            console.log('[Service Worker] Removing old cache:', key);
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event (Network falling back to Cache)
self.addEventListener('fetch', (event) => {
  // Only handle GET requests and exclude dynamic API endpoints
  if (event.request.method !== 'GET' || event.request.url.includes('/api/')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // If we got a valid response, clone it and save to cache
        if (response && response.status === 200 && response.type === 'basic') {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => {
        // Fallback to cache if network fails (offline support)
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          // If neither works and it's a page navigation request, return login page
          if (event.request.headers.get('accept').includes('text/html')) {
            return caches.match('/login.php');
          }
        });
      })
  );
});
