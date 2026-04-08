const CACHE_NAME = 'almoustafa-static-v14 ';
const PRECACHE_ASSETS = [
  './',
  'index.html',
  'products.html',
  'collections.html',
  'reviews.html',
  'checkout.html',
  'onboarding.html',
  'contact.html',
  'about.html',
  'powerd-by.html',
  'icons/bootstrap-icons/bootstrap-icons.min.css',
  'icons/bootstrap-icons/fonts/bootstrap-icons.woff2',
  'icons/bootstrap-icons/fonts/bootstrap-icons.woff',
  'logo.png',
  'pics/collections/honey.jpg',
  'pics/collections/derivatives.jpg',
  'pics/collections/beauty.jpg',
  'pics/collections/dates.jpg',
  'pics/collections/nuts.jpg',
  'js/cookie-consent.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  const isStaticAsset = /\.(?:css|js|woff2?|png|jpg|jpeg|webp|svg|gif)$/i.test(url.pathname);
  if (isStaticAsset) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) {
          return cached;
        }
        return fetch(request).then((response) => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          return response;
        });
      })
    );
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match('index.html')))
    );
  }
});
