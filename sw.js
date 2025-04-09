// Basic service worker for PWA
const CACHE_NAME = 'salaam-rides-v1';
const urlsToCache = [
  '/',
  '/assets/css/style.css',
  '/assets/js/driver-dashboard.js',
  '/assets/img/islamic-pattern.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => response || fetch(event.request))
  );
});

// Background sync for offline location updates
self.addEventListener('sync', event => {
  if (event.tag === 'sync-location') {
    event.waitUntil(syncLocationUpdates());
  }
});

// Process any queued location updates
async function syncLocationUpdates() {
  const db = await openDB();
  const tx = db.transaction('locationUpdates', 'readwrite');
  const store = tx.objectStore('locationUpdates');
  
  const updates = await store.getAll();
  
  for (const update of updates) {
    try {
      const response = await fetch('/api/driver-location-update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(update.data)
      });
      
      if (response.ok) {
        await store.delete(update.id);
      }
    } catch (error) {
      console.error('Failed to sync update:', error);
    }
  }
}

// IndexedDB helper
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('SalaamRidesDB', 1);
    
    request.onerror = e => reject(e.target.error);
    request.onsuccess = e => resolve(e.target.result);
    
    request.onupgradeneeded = e => {
      const db = e.target.result;
      db.createObjectStore('locationUpdates', { keyPath: 'id', autoIncrement: true });
    };
  });
}