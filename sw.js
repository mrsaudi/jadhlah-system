// sw.js
const CACHE_NAME = 'jadhlah-v1';

self.addEventListener('install', (event) => {
    console.log('Service Worker: Installing...');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('Service Worker: Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

self.addEventListener('push', (event) => {
    console.log('Push notification received:', event);
    
    let data = {
        title: '🎉 إشعار جديد',
        body: 'لديك تحديث جديد',
        icon: '/assets/icon-192x192.png',
        badge: '/assets/badge-72x72.png',
        data: { url: 'https://jadhlah.com' }
    };
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            console.error('Error parsing notification:', e);
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon || '/assets/icon-192x192.png',
            badge: data.badge || '/assets/badge-72x72.png',
            tag: data.tag || 'jadhlah-notification',
            requireInteraction: data.requireInteraction || false,
            vibrate: data.vibrate || [200, 100, 200],
            data: data.data || {},
            actions: data.actions || [
                { action: 'open', title: 'فتح' },
                { action: 'close', title: 'إغلاق' }
            ]
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    console.log('Notification clicked:', event);
    event.notification.close();
    
    if (event.action === 'open' || !event.action) {
        const url = event.notification.data.url || 'https://jadhlah.com';
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then((clientList) => {
                    for (let client of clientList) {
                        if (client.url === url && 'focus' in client) {
                            return client.focus();
                        }
                    }
                    if (clients.openWindow) {
                        return clients.openWindow(url);
                    }
                })
        );
    }
});