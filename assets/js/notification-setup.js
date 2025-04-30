class NotificationSetup {
    constructor() {
        this.serviceWorkerRegistration = null;
        this.pushSubscription = null;
        this.vapidPublicKey = sandcrimeConfig.vapidPublicKey;

        this.init();
    }

    async init() {
        try {
            await this.registerServiceWorker();
            await this.checkNotificationPermission();
            await this.setupPushSubscription();
        } catch (error) {
            console.error('Notification setup failed:', error);
        }
    }

    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            throw new Error('Service Worker not supported');
        }

        try {
            this.serviceWorkerRegistration = await navigator.serviceWorker.register(
                '/wp-content/plugins/sandcrime/assets/js/service-worker.js',
                { scope: '/' }
            );

            navigator.serviceWorker.ready.then(registration => {
                console.log('Service Worker ready');
            });

            // Handle service worker updates
            this.serviceWorkerRegistration.addEventListener('updatefound', () => {
                const newWorker = this.serviceWorkerRegistration.installing;
                
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdateNotification();
                    }
                });
            });
        } catch (error) {
            throw new Error(`Service Worker registration failed: ${error.message}`);
        }
    }

    async checkNotificationPermission() {
        if (!('Notification' in window)) {
            throw new Error('Notifications not supported');
        }

        if (Notification.permission === 'denied') {
            throw new Error('Notification permission denied');
        }

        if (Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                throw new Error('Notification permission not granted');
            }
        }
    }

    async setupPushSubscription() {
        if (!this.serviceWorkerRegistration) {
            throw new Error('Service Worker not registered');
        }

        try {
            // Get existing subscription
            this.pushSubscription = await this.serviceWorkerRegistration.pushManager.getSubscription();

            if (this.pushSubscription) {
                // Validate existing subscription
                await this.validateSubscription();
            } else {
                // Create new subscription
                await this.subscribeForPushNotifications();
            }
        } catch (error) {
            throw new Error(`Push subscription setup failed: ${error.message}`);
        }
    }

    async validateSubscription() {
        try {
            const response = await fetch(sandcrimeConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'validate_push_subscription',
                    nonce: sandcrimeConfig.nonce,
                    subscription: this.pushSubscription.toJSON()
                })
            });

            const data = await response.json();
            if (!data.success) {
                // Subscription invalid, create new one
                await this.pushSubscription.unsubscribe();
                await this.subscribeForPushNotifications();
            }
        } catch (error) {
            throw new Error(`Subscription validation failed: ${error.message}`);
        }
    }

    async subscribeForPushNotifications() {
        try {
            const applicationServerKey = this.urlB64ToUint8Array(this.vapidPublicKey);
            
            this.pushSubscription = await this.serviceWorkerRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });

            // Send subscription to server
            await this.sendSubscriptionToServer();
        } catch (error) {
            throw new Error(`Push subscription failed: ${error.message}`);
        }
    }

    async sendSubscriptionToServer() {
        try {
            const response = await fetch(sandcrimeConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save_push_subscription',
                    nonce: sandcrimeConfig.nonce,
                    subscription: this.pushSubscription.toJSON(),
                    device_info: {
                        userAgent: navigator.userAgent,
                        platform: navigator.platform,
                        language: navigator.language
                    }
                })
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.data.message || 'Failed to save subscription');
            }
        } catch (error) {
            throw new Error(`Failed to send subscription to server: ${error.message}`);
        }
    }

    showUpdateNotification() {
        const toast = new NotificationToast({
            title: 'Update Available',
            message: 'A new version is available. Reload to update.',
            type: 'system',
            duration: 0,
            onClick: () => {
                window.location.reload();
            }
        });
        toast.show();
    }

    urlB64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async unsubscribe() {
        if (this.pushSubscription) {
            try {
                await this.pushSubscription.unsubscribe();
                
                // Notify server
                await fetch(sandcrimeConfig.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'remove_push_subscription',
                        nonce: sandcrimeConfig.nonce,
                        subscription: this.pushSubscription.toJSON()
                    })
                });

                this.pushSubscription = null;
                return true;
            } catch (error) {
                console.error('Unsubscription failed:', error);
                return false;
            }
        }
        return true;
    }
}

// Initialize notification setup when document is ready
$(document).ready(() => {
    if (sandcrimeConfig.enablePushNotifications) {
        window.notificationSetup = new NotificationSetup();
    }
});