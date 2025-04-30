const CACHE_NAME = 'sandcrime-cache-v1';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll([
                    OFFLINE_URL,
                    '/assets/css/notifications.css',
                    '/assets/images/icons/notification-icon.png'
                ]);
            })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }
            }));
        })
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return caches.match(OFFLINE_URL);
                })
        );
    } else {
        event.respondWith(
            caches.match(event.request)
                .then((response) => {
                    return response || fetch(event.request);
                })
        );
    }
});

self.addEventListener('push', (event) => {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const data = event.data.json();
    const options = {
        body: data.message,
        icon: '/assets/images/icons/notification-icon.png',
        badge: '/assets/images/icons/badge-icon.png',
        tag: `notification-${data.id}`,
        data: data.data,
        actions: getNotificationActions(data.type),
        requireInteraction: data.priority === 'urgent'
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Handle notification action clicks
    if (event.action) {
        handleNotificationAction(event.action, event.notification.data);
        return;
    }

    // Handle general notification click
    event.waitUntil(
        handleNotificationClick(event.notification.data)
    );
});

function getNotificationActions(type) {
    switch (type) {
        case 'group_message':
            return [
                {
                    action: 'reply',
                    title: 'Reply'
                },
                {
                    action: 'view',
                    title: 'View Message'
                }
            ];
        case 'group_alert':
            return [
                {
                    action: 'acknowledge',
                    title: 'Acknowledge'
                },
                {
                    action: 'view',
                    title: 'View Details'
                }
            ];
        case 'report':
            return [
                {
                    action: 'view',
                    title: 'View Report'
                }
            ];
        default:
            return [];
    }
}

function handleNotificationAction(action, data) {
    let url;
    switch (action) {
        case 'reply':
            url = `/groups/${data.group_id}/messages?reply=${data.message_id}`;
            break;
        case 'acknowledge':
            url = `/groups/${data.group_id}/alerts/${data.alert_id}/acknowledge`;
            break;
        case 'view':
            url = getNotificationUrl(data);
            break;
    }

    if (url) {
        openUrl(url);
    }
}

function handleNotificationClick(data) {
    const url = getNotificationUrl(data);
    return openUrl(url);
}

function getNotificationUrl(data) {
    switch (data.type) {
        case 'group_message':
            return `/groups/${data.group_id}/messages#message-${data.message_id}`;
        case 'group_alert':
            return `/groups/${data.group_id}/alerts#alert-${data.alert_id}`;
        case 'report':
            return `/reports/${data.report_id}`;
        default:
            return '/notifications';
    }
}

function openUrl(url) {
    const baseUrl = self.location.origin;
    const fullUrl = new URL(url, baseUrl).href;

    return clients.matchAll({
        type: 'window',
        includeUncontrolled: true
    }).then((windowClients) => {
        // Try to focus an existing window
        for (const client of windowClients) {
            if (client.url === fullUrl) {
                return client.focus();
            }
        }
        // Open a new window
        return clients.openWindow(fullUrl);
    });
}