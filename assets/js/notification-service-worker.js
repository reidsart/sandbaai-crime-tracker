'use strict';

const CACHE_NAME = 'sandcrime-notifications-v1';
const OFFLINE_URL = '/offline/';

self.addEventListener('install', event => {
    event.waitUntil(
        Promise.all([
            cacheOfflinePage(),
            self.skipWaiting()
        ])
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        Promise.all([
            cleanupOldCaches(),
            self.clients.claim()
        ])
    );
});

self.addEventListener('push', event => {
    if (!event.data) {
        return;
    }

    const notification = event.data.json();
    event.waitUntil(handlePushNotification(notification));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    event.waitUntil(handleNotificationClick(event));
});

self.addEventListener('fetch', event => {
    if (event.request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(event));
    }
});

async function cacheOfflinePage() {
    const cache = await caches.open(CACHE_NAME);
    try {
        await cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));
    } catch (error) {
        console.error('Failed to cache offline page:', error);
    }
}

async function cleanupOldCaches() {
    const cacheKeys = await caches.keys();
    const deletionPromises = cacheKeys
        .filter(key => key !== CACHE_NAME)
        .map(key => caches.delete(key));
    
    await Promise.all(deletionPromises);
}

async function handlePushNotification(notification) {
    const { title, options } = formatNotification(notification);

    try {
        // Track notification delivery
        await trackNotificationDelivery(notification.id);

        // Show the notification
        return self.registration.showNotification(title, options);
    } catch (error) {
        console.error('Failed to handle push notification:', error);
    }
}

function formatNotification(data) {
    const options = {
        body: data.message,
        icon: data.icon || '/wp-content/plugins/sandcrime/assets/images/notification-icon.png',
        badge: data.badge || '/wp-content/plugins/sandcrime/assets/images/notification-badge.png',
        tag: data.id,
        data: {
            url: data.url,
            id: data.id,
            actions: data.actions
        },
        requireInteraction: data.requireInteraction || false,
        renotify: data.renotify || false,
        silent: data.silent || false,
        timestamp: data.timestamp || Date.now()
    };

    // Add vibration if supported and enabled
    if (data.vibrate) {
        options.vibrate = [200, 100, 200];
    }

    // Add actions if provided
    if (Array.isArray(data.actions)) {
        options.actions = data.actions.map(action => ({
            action: action.id,
            title: action.title,
            icon: action.icon || undefined
        }));
    }

    return {
        title: data.title,
        options: options
    };
}

async function handleNotificationClick(event) {
    const notification = event.notification;
    const data = notification.data;
    const action = event.action;

    // Track notification interaction
    await trackNotificationInteraction(data.id, action);

    // Handle specific actions
    if (action) {
        return handleNotificationAction(action, data);
    }

    // Default click behavior - open URL if provided
    if (data.url) {
        const allClients = await clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        // Try to focus an existing window
        for (const client of allClients) {
            if (client.url === data.url) {
                return client.focus();
            }
        }

        // Open new window if no existing window found
        return clients.openWindow(data.url);
    }
}

async function handleNotificationAction(action, data) {
    switch (action) {
        case 'dismiss':
            // Handle dismiss action
            return trackNotificationDismissal(data.id);

        case 'reply':
            // Open reply interface
            return clients.openWindow(`${data.url}#reply`);

        case 'archive':
            // Archive the notification
            try {
                await archiveNotification(data.id);
                // Refresh any open windows
                const allClients = await clients.matchAll();
                allClients.forEach(client => client.postMessage({
                    type: 'NOTIFICATION_ARCHIVED',
                    id: data.id
                }));
            } catch (error) {
                console.error('Failed to archive notification:', error);
            }
            break;

        default:
            // Handle custom actions
            if (data.actions && data.actions[action]) {
                return clients.openWindow(data.actions[action].url);
            }
    }
}

async function handleNavigationRequest(event) {
    try {
        const preloadResponse = await event.preloadResponse;
        if (preloadResponse) {
            return preloadResponse;
        }

        const networkResponse = await fetch(event.request);
        return networkResponse;
    } catch (error) {
        const cache = await caches.open(CACHE_NAME);
        const cachedResponse = await cache.match(OFFLINE_URL);
        return cachedResponse;
    }
}

async function trackNotificationDelivery(notificationId) {
    try {
        await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'track_notification_delivery',
                notification_id: notificationId
            })
        });
    } catch (error) {
        console.error('Failed to track notification delivery:', error);
    }
}

async function trackNotificationInteraction(notificationId, action) {
    try {
        await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'track_notification_interaction',
                notification_id: notificationId,
                interaction_type: action || 'click'
            })
        });
    } catch (error) {
        console.error('Failed to track notification interaction:', error);
    }
}

async function trackNotificationDismissal(notificationId) {
    try {
        await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'track_notification_dismissal',
                notification_id: notificationId
            })
        });
    } catch (error) {
        console.error('Failed to track notification dismissal:', error);
    }
}

async function archiveNotification(notificationId) {
    try {
        const response = await fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'archive_notification',
                notification_id: notificationId
            })
        });

        if (!response.ok) {
            throw new Error('Failed to archive notification');
        }
    } catch (error) {
        console.error('Failed to archive notification:', error);
        throw error;
    }
}