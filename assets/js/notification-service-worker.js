'use strict';

const CACHE_NAME = 'sandcrime-notifications-v1';
const OFFLINE_URL = '/offline-notifications';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll([
                OFFLINE_URL,
                '/wp-content/plugins/sandcrime/assets/images/notification-icon.png'
            ]))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.filter((cacheName) => {
                    return cacheName.startsWith('sandcrime-') && cacheName !== CACHE_NAME;
                }).map((cacheName) => {
                    return caches.delete(cacheName);
                })
            );
        })
    );
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();
    const options = {
        body: payload.message,
        icon: '/wp-content/plugins/sandcrime/assets/images/notification-icon.png',
        badge: '/wp-content/plugins/sandcrime/assets/images/notification-badge.png',
        tag: `sandcrime-notification-${payload.id}`,
        data: payload,
        requireInteraction: payload.priority === 'urgent',
        actions: generateNotificationActions(payload)
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const payload = event.notification.data;
    let url = '/notifications';

    if (event.action) {
        switch (event.action) {
            case 'view':
                url = payload.data.url || url;
                break;
            case 'acknowledge':
                event.waitUntil(acknowledgeAlert(payload.data.alert_id));
                return;
        }
    } else {
        url = payload.data.url || url;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window' })
            .then((clientList) => {
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

function generateNotificationActions(payload) {
    const actions = [];

    if (payload.data && payload.data.url) {
        actions.push({
            action: 'view',
            title: 'View'
        });
    }

    if (payload.type === 'group_alert' && payload.data && payload.data.alert_id) {
        actions.push({
            action: 'acknowledge',
            title: 'Acknowledge'
        });
    }

    return actions;
}

async function acknowledgeAlert(alertId) {
    try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'acknowledge_alert',
                nonce: self.registration.scope.split('?')[1],
                alert_id: alertId
            })
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.data.message);
        }
    } catch (error) {
        console.error('Failed to acknowledge alert:', error);
    }
}

self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-notifications') {
        event.waitUntil(syncNotifications());
    }
});

async function syncNotifications() {
    try {
        const cache = await caches.open(CACHE_NAME);
        const requests = await cache.keys();
        
        for (const request of requests) {
            if (request.url.includes('/notifications/sync')) {
                const response = await fetch(request);
                if (response.ok) {
                    await cache.delete(request);
                }
            }
        }
    } catch (error) {
        console.error('Failed to sync notifications:', error);
    }
}

self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'update-notifications') {
        event.waitUntil(updateNotifications());
    }
});

async function updateNotifications() {
    try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_notifications',
                nonce: self.registration.scope.split('?')[1]
            })
        });

        const data = await response.json();
        if (data.success && data.data.notifications.length > 0) {
            for (const notification of data.data.notifications) {
                await self.registration.showNotification(
                    notification.title,
                    {
                        body: notification.message,
                        icon: '/wp-content/plugins/sandcrime/assets/images/notification-icon.png',
                        data: notification
                    }
                );
            }
        }
    } catch (error) {
        console.error('Failed to update notifications:', error);
    }
}

self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        self.registration.pushManager.subscribe(event.oldSubscription.options)
            .then((subscription) => {
                return fetch('/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'update_push_subscription',
                        nonce: self.registration.scope.split('?')[1],
                        old_endpoint: event.oldSubscription.endpoint,
                        new_subscription: subscription
                    })
                });
            })
    );
});