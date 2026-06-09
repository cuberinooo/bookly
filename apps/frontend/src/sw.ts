/// <reference lib="webworker" />
import { precacheAndRoute } from 'workbox-precaching';

declare const self: ServiceWorkerGlobalScope;

// Injected by workbox during bundling
precacheAndRoute(self.__WB_MANIFEST || []);

// Push Event Listener
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let title = 'BooklyFit';
    const options: NotificationOptions = {
        icon: '/logo.png',
        badge: '/logo.png',
        data: {
            url: '/'
        }
    };

    try {
        const payload = event.data.json();
        title = payload.title || title;
        options.body = payload.body || '';
        if (payload.url) {
            options.data.url = payload.url;
        }
    } catch (e) {
        // Fallback if data is not JSON
        options.body = event.data.text();
    }

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Notification Click Event Listener
self.addEventListener('notificationclick', (event) => {
    const notification = event.notification;
    notification.close();

    const urlToOpen = new URL(notification.data.url || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then((windowClients) => {
            // Check if a client window with this URL is already open
            for (let i = 0; i < windowClients.length; i++) {
                const client = windowClients[i];
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            // If no window is open or it's a different URL, open a new one
            if (self.clients.openWindow) {
                return self.clients.openWindow(urlToOpen);
            }
        })
    );
});
