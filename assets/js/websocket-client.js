class NotificationWebSocket {
    constructor() {
        this.pusher = null;
        this.channels = {};
        this.connected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 2000;

        this.initializePusher();
    }

    initializePusher() {
        this.pusher = new Pusher(sandcrimeConfig.pusherKey, {
            cluster: sandcrimeConfig.pusherCluster,
            authEndpoint: sandcrimeConfig.ajaxUrl,
            auth: {
                params: {
                    action: 'get_websocket_auth',
                    nonce: sandcrimeConfig.wsAuthNonce
                }
            }
        });

        this.setupConnectionHandlers();
        this.subscribeToUserChannel();
    }

    setupConnectionHandlers() {
        this.pusher.connection.bind('connected', () => {
            this.connected = true;
            this.reconnectAttempts = 0;
            console.log('WebSocket connected');
        });

        this.pusher.connection.bind('disconnected', () => {
            this.connected = false;
            console.log('WebSocket disconnected');
            this.attemptReconnect();
        });

        this.pusher.connection.bind('error', error => {
            console.error('WebSocket error:', error);
            if (error.error.data.code === 4004) {
                // Authentication error - refresh the page to get new credentials
                window.location.reload();
            }
        });
    }

    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnection attempts reached');
            return;
        }

        setTimeout(() => {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
            this.pusher.connect();
        }, this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1));
    }

    subscribeToUserChannel() {
        const channelName = `private-user-${sandcrimeConfig.userId}`;
        this.channels.user = this.pusher.subscribe(channelName);

        this.channels.user.bind('notification.new', data => {
            this.handleNewNotification(data);
        });

        this.channels.user.bind('notification.read', data => {
            this.handleNotificationRead(data);
        });

        this.channels.user.bind('pusher:subscription_error', error => {
            console.error('Channel subscription error:', error);
        });
    }

    subscribeToGroupChannel(groupId) {
        const channelName = `private-group-${groupId}`;
        if (this.channels[channelName]) {
            return;
        }

        this.channels[channelName] = this.pusher.subscribe(channelName);

        this.channels[channelName].bind('group.message', data => {
            this.handleGroupMessage(data);
        });

        this.channels[channelName].bind('group.alert', data => {
            this.handleGroupAlert(data);
        });
    }

    unsubscribeFromGroupChannel(groupId) {
        const channelName = `private-group-${groupId}`;
        if (this.channels[channelName]) {
            this.pusher.unsubscribe(channelName);
            delete this.channels[channelName];
        }
    }

    handleNewNotification(data) {
        // Update notification UI
        if (window.notificationCenter) {
            window.notificationCenter.handleNewNotification(data);
        }

        // Show toast notification
        this.showNotificationToast(data);

        // Update badge count
        this.updateNotificationBadge();

        // Trigger custom event
        const event = new CustomEvent('sandcrimeNotification', { detail: data });
        document.dispatchEvent(event);
    }

    handleNotificationRead(data) {
        if (window.notificationCenter) {
            window.notificationCenter.handleNotificationRead(data.notification_id);
        }
        this.updateNotificationBadge();
    }

    handleGroupMessage(data) {
        if (window.messageBoard) {
            window.messageBoard.handleNewMessage(data);
        }
        
        if (!data.is_sender) {
            this.showNotificationToast({
                title: 'New Group Message',
                message: data.message_preview,
                type: 'group_message',
                data: {
                    group_id: data.group_id,
                    message_id: data.message_id
                }
            });
        }
    }

    handleGroupAlert(data) {
        if (window.messageBoard) {
            window.messageBoard.handleNewAlert(data);
        }

        this.showNotificationToast({
            title: 'Group Alert',
            message: data.message,
            type: 'group_alert',
            data: {
                group_id: data.group_id,
                alert_id: data.alert_id
            }
        });
    }

    showNotificationToast(data) {
        const toast = new NotificationToast({
            title: data.title,
            message: data.message,
            type: data.type,
            duration: 5000,
            onClick: () => {
                this.handleToastClick(data);
            }
        });
        toast.show();
    }

    handleToastClick(data) {
        let url;
        switch (data.type) {
            case 'group_message':
                url = `${sandcrimeConfig.baseUrl}/groups/${data.data.group_id}/messages#message-${data.data.message_id}`;
                break;
            case 'group_alert':
                url = `${sandcrimeConfig.baseUrl}/groups/${data.data.group_id}/alerts#alert-${data.data.alert_id}`;
                break;
            default:
                url = `${sandcrimeConfig.baseUrl}/notifications`;
        }
        window.location.href = url;
    }

    updateNotificationBadge() {
        $.ajax({
            url: sandcrimeConfig.ajaxUrl,
            method: 'GET',
            data: {
                action: 'get_unread_count',
                nonce: sandcrimeConfig.nonce
            },
            success: response => {
                if (response.success) {
                    const count = response.data.count;
                    $('.notification-badge').text(count > 0 ? count : '');
                    $('.notification-badge').toggle(count > 0);
                }
            }
        });
    }

    disconnect() {
        this.pusher.disconnect();
    }
}

// Initialize WebSocket client when document is ready
$(document).ready(() => {
    window.notificationSocket = new NotificationWebSocket();
});