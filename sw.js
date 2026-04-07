/* Sharity PWA Service Worker */
const CACHE_VERSION = '20260331-3';
const STATIC_CACHE = `pwa-static-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [OFFLINE_URL];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('pwa-static-') && key !== STATIC_CACHE)
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

function isStaticAsset(pathname) {
  return (/\.(?:css|js|png|jpg|jpeg|webp|svg|gif|ico|woff2?)$/i).test(pathname);
}

function shouldSkip(request, url) {
  if (request.method !== 'GET') return true;
  if (url.origin !== self.location.origin) return true;
  if (url.searchParams.has('rest_route')) return true;

  const skipPaths = [
    '/wp-admin',
    '/wp-login',
    '/wp-json',
    '/go/',
    '/go-deal/',
    '/impactad-2',
    '/wp-cron.php',
    '/xmlrpc.php',
    '/wp-content/uploads/ngo_codes.csv',
    '/partner-api'
  ];
  if (skipPaths.some((path) => url.pathname.startsWith(path))) return true;
  if (url.searchParams.get('preview') === 'true') return true;

  const accept = request.headers.get('accept') || '';
  const hasCookie = request.headers.get('cookie');
  if (accept.includes('text/html') && hasCookie) return true;

  return false;
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);
  const fetchPromise = fetch(request).then((response) => {
    if (response && response.status === 200) {
      cache.put(request, response.clone());
    }
    return response;
  });
  return cached || fetchPromise;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  if (shouldSkip(request, url)) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  if (isStaticAsset(url.pathname)) {
    event.respondWith(staleWhileRevalidate(request));
  }
});

self.addEventListener('push', (event) => {
  let payload = { title: 'Sharity', body: '', url: '/profil' };
  if (event.data) {
    try {
      const data = event.data.json();
      if (data && typeof data === 'object') {
        payload = Object.assign(payload, data);
      }
    } catch (e) {
      payload.body = event.data.text();
    }
  }
  const title = payload.title || 'Sharity';
  const options = {
    body: payload.body || '',
    icon: '/wp-content/uploads/pwa-icons/icon-192x192.png',
    badge: '/wp-content/uploads/pwa-icons/icon-192x192.png',
    data: {
      url: payload.url || '/profil',
      messageId: payload.messageId || null
    }
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  const url = event.notification && event.notification.data ? event.notification.data.url : '/profil';
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url && client.url.indexOf(url) !== -1 && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
      return null;
    })
  );
});
