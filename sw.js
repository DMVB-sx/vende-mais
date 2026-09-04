const CACHE_NAME = 'vendemais-v2';
const STATIC_ASSETS = [
  '/',
  '/manifest.json',
  '/assets/css/style.css',
  '/assets/img/favicon.svg',
  '/assets/img/icon-192.png',
  '/assets/img/icon-512.png'
];

// Instalação
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// Ativação e limpeza de cache antigo
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

// Interceptação segura
self.addEventListener('fetch', (event) => {
  // 1. Ignora requisições POST ou não GET (Safari quebra se tentar responder com null)
  if (event.request.method !== 'GET') {
    return;
  }

  // 2. Busca na rede primeiro, se falhar tenta o cache
  event.respondWith(
    fetch(event.request)
      .catch(async () => {
        const cachedResponse = await caches.match(event.request);
        if (cachedResponse) {
          return cachedResponse;
        }
        // Fallback seguro caso não ache nada no cache
        return caches.match('/') || Response.error();
      })
  );
});