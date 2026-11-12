const CACHE_NAME = 'sms-cafe-static-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/erp-mark.svg', '/images/out-of-boredom-logo.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => (
                        (
                            cacheName.startsWith('sms-cafe-static-')
                            || cacheName.startsWith('out-of-boredom-static-')
                            || cacheName.startsWith('northstar-static-')
                        )
                        && cacheName !== CACHE_NAME
                    ))
                    .map((cacheName) => caches.delete(cacheName)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const requestUrl = new URL(event.request.url);

    if (event.request.method !== 'GET' || requestUrl.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(async () => (
            (await caches.match(OFFLINE_URL)) || Response.error()
        )));

        return;
    }

    if (/\.(?:css|js|svg|png|webp|woff2?)$/i.test(requestUrl.pathname)) {
        event.respondWith(
            fetch(event.request).then((response) => {
                if (response.ok) {
                    const responseCopy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseCopy));
                }

                return response;
            }).catch(async () => (await caches.match(event.request)) || Response.error()),
        );
    }
});
