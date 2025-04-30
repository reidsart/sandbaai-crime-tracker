class NotificationPreferences {
    constructor() {
        this.form = document.getElementById('notification-preferences-form');
        this.testButton = document.getElementById('test-notifications');
        this.hasUnsavedChanges = false;

        this.initializeComponents();
        this.bindEvents();
    }

    initializeComponents() {
        // Initialize Select2 for multiple select boxes
        jQuery('.notification-types-select').select2({
            width: '100%',
            placeholder: sandcrimePrefs.i18n.selectTypes,
            allowClear: true
        });

        // Initialize time pickers
        this.initializeTimePickers();

        // Initialize tooltips
        this.initializeTooltips();

        // Set initial state of dependent fields
        this.updateDependentFields();
    }

    initializeTimePickers() {
        jQuery('.time-picker').timepicker({
            timeFormat: 'HH:mm',
            interval: 30,
            minTime: '00:00',
            maxTime: '23:30',
            defaultTime: '00:00',
            startTime: '00:00',
            dynamic: false,
            dropdown: true,
            scrollbar: true,
            change: () => this.markAsUnsaved()
        });
    }

    initializeTooltips() {
        jQuery('[data-tooltip]').tooltip({
            position: {
                my: 'left+10 center',
                at: 'right center'
            }
        });
    }

    bindEvents() {
        // Form change detection
        this.form.addEventListener('change', () => this.markAsUnsaved());
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Email notification toggle
        document.getElementById('email_enabled').addEventListener('change', (e) => {
            this.toggleEmailSettings(e.target.checked);
        });

        // Push notification toggle
        document.getElementById('push_enabled').addEventListener('change', (e) => {
            this.togglePushSettings(e.target.checked);
        });

        // Quiet hours toggle
        document.getElementById('quiet_hours_enabled').addEventListener('change', (e) => {
            this.toggleQuietHours(e.target.checked);
        });

        // Test notification button
        this.testButton.addEventListener('click', () => this.sendTestNotification());

        // Device management
        document.querySelectorAll('.remove-device').forEach(button => {
            button.addEventListener('click', (e) => this.removeDevice(e));
        });

        // Unsaved changes warning
        window.addEventListener('beforeunload', (e) => this.handleUnsavedChanges(e));
    }

    markAsUnsaved() {
        this.hasUnsavedChanges = true;
        document.getElementById('save-preferences').classList.add('button-primary');
    }

    async handleSubmit(e) {
        e.preventDefault();

        const formData = new FormData(this.form);
        const preferences = Object.fromEntries(formData);

        try {
            await this.savePreferences(preferences);
            this.hasUnsavedChanges = false;
            this.showSuccess(sandcrimePrefs.i18n.preferencesSaved);
            document.getElementById('save-preferences').classList.remove('button-primary');
        } catch (error) {
            this.showError(error.message);
        }
    }

    async savePreferences(preferences) {
        const response = await fetch(sandcrimePrefs.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_notification_preferences',
                nonce: sandcrimePrefs.nonce,
                preferences: preferences
            })
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.data || sandcrimePrefs.i18n.saveError);
        }

        return data;
    }

    toggleEmailSettings(enabled) {
        const emailSettings = document.getElementById('email-settings');
        emailSettings.style.display = enabled ? 'block' : 'none';
        
        // Update required attributes
        emailSettings.querySelectorAll('input, select').forEach(input => {
            input.required = enabled && input.dataset.required === 'true';
        });
    }

    togglePushSettings(enabled) {
        const pushSettings = document.getElementById('push-settings');
        pushSettings.style.display = enabled ? 'block' : 'none';

        if (enabled) {
            this.requestPushPermission();
        }
    }

    toggleQuietHours(enabled) {
        const quietHoursSettings = document.querySelector('.quiet-hours-settings');
        quietHoursSettings.style.display = enabled ? 'block' : 'none';
    }

    updateDependentFields() {
        this.toggleEmailSettings(document.getElementById('email_enabled').checked);
        this.togglePushSettings(document.getElementById('push_enabled').checked);
        this.toggleQuietHours(document.getElementById('quiet_hours_enabled').checked);
    }

    async requestPushPermission() {
        if (!('Notification' in window)) {
            this.showError(sandcrimePrefs.i18n.pushNotSupported);
            return;
        }

        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                await this.registerServiceWorker();
            } else {
                this.showError(sandcrimePrefs.i18n.pushDenied);
                document.getElementById('push_enabled').checked = false;
            }
        } catch (error) {
            console.error('Push permission error:', error);
            this.showError(sandcrimePrefs.i18n.pushError);
            document.getElementById('push_enabled').checked = false;
        }
    }

    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register(
                sandcrimePrefs.serviceWorkerUrl,
                { scope: '/' }
            );

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(sandcrimePrefs.vapidPublicKey)
            });

            await this.savePushSubscription(subscription);
        } catch (error) {
            console.error('Service Worker registration failed:', error);
            this.showError(sandcrimePrefs.i18n.serviceWorkerError);
        }
    }

    async savePushSubscription(subscription) {
        try {
            const response = await fetch(sandcrimePrefs.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save_push_subscription',
                    nonce: sandcrimePrefs.nonce,
                    subscription: subscription
                })
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.data);
            }
        } catch (error) {
            console.error('Failed to save push subscription:', error);
            this.showError(sandcrimePrefs.i18n.subscriptionError);
        }
    }

    async sendTestNotification() {
        this.testButton.disabled = true;
        const spinner = this.testButton.nextElementSibling;
        spinner.style.visibility = 'visible';

        try {
            const response = await fetch(sandcrimePrefs.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'test_notification_preferences',
                    nonce: sandcrimePrefs.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showSuccess(sandcrimePrefs.i18n.testSuccess);
            } else {
                throw new Error(data.data);
            }
        } catch (error) {
            this.showError(sandcrimePrefs.i18n.testError);
        } finally {
            this.testButton.disabled = false;
            spinner.style.visibility = 'hidden';
        }
    }

    async removeDevice(e) {
        const deviceId = e.target.dataset.deviceId;
        if (!confirm(sandcrimePrefs.i18n.confirmRemoveDevice)) {
            return;
        }

        try {
            const response = await fetch(sandcrimePrefs.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove_notification_device',
                    nonce: sandcrimePrefs.nonce,
                    device_id: deviceId
                })
            });

            const data = await response.json();
            if (data.success) {
                e.target.closest('tr').remove();
                this.showSuccess(sandcrimePrefs.i18n.deviceRemoved);
            } else {
                throw new Error(data.data);
            }
        } catch (error) {
            this.showError(sandcrimePrefs.i18n.deviceRemoveError);
        }
    }

    handleUnsavedChanges(e) {
        if (this.hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = sandcrimePrefs.i18n.unsavedChanges;
            return e.returnValue;
        }
    }

    showSuccess(message) {
        this.showNotice(message, 'success');
    }

    showError(message) {
        this.showNotice(message, 'error');
    }

    showNotice(message, type) {
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${message}</p>`;

        const wrapper = document.querySelector('.wrap');
        wrapper.insertBefore(notice, wrapper.firstChild);

        // Initialize WordPress dismissible notices
        jQuery(document).trigger('wp-updates-notice-added');

        setTimeout(() => {
            notice.remove();
        }, 5000);
    }

    urlBase64ToUint8Array(base64String) {
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
}

// Initialize preferences when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationPreferences = new NotificationPreferences();
});