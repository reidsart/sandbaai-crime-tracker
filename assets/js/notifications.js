(function($) {
    'use strict';

    class NotificationManager {
        constructor() {
            this.pusher = null;
            this.channel = null;
            this.notificationCount = 0;
            this.notifications = [];
            this.unreadCount = 0;

            this.initializePusher();
            this.initializeEventListeners();
            this.loadNotifications();
        }

        initializePusher() {
            // Initialize Pusher with your app key
            this.pusher = new Pusher(sandcrimeNotifications.websocketKey, {
                cluster: sandcrimeNotifications.websocketCluster,
                authEndpoint: sandcrimeNotifications.ajaxUrl,
                auth: {
                    params: {
                        action: 'get_websocket_auth',
                        nonce: sandcrimeNotifications.nonce
                    }
                }
            });

            // Subscribe to user's private channel
            this.channel = this.pusher.subscribe(
                'private-user-' + sandcrimeNotifications.userId
            );

            // Bind to notification events
            this.channel.bind('report.update', (data) => {
                this.handleReportUpdate(data);
            });

            this.channel.bind('area.report', (data) => {
                this.handleAreaReport(data);
            });

            this.channel.bind('group.update', (data) => {
                this.handleGroupUpdate(data);
            });

            this.channel.bind('comment.new', (data) => {
                this.handleNewComment(data);
            });
        }

        initializeEventListeners() {
            // Toggle notification panel
            $('#notification-toggle').on('click', () => {
                this.toggleNotificationPanel();
            });

            // Mark notification as read
            $(document).on('click', '.notification-item', (e) => {
                const notificationId = $(e.currentTarget).data('id');
                this.markAsRead(notificationId);
            });

            // Clear all notifications
            $('#clear-notifications').on('click', () => {
                this.clearAllNotifications();
            });

            // Load more notifications
            $('#load-more-notifications').on('click', () => {
                this.loadMoreNotifications();
            });
        }

        loadNotifications() {
            $.ajax({
                url: sandcrimeNotifications.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_user_notifications',
                    nonce: sandcrimeNotifications.nonce,
                    page: 1
                },
                success: (response) => {
                    if (response.success) {
                        this.notifications = response.data.notifications;
                        this.unreadCount = response.data.unread_count;
                        this.updateNotificationPanel();
                        this.updateNotificationBadge();
                    }
                }
            });
        }

        handleReportUpdate(data) {
            this.addNotification({
                type: 'report_update',
                title: 'Report Update',
                message: `Your report "${data.report_title}" has been updated`,
                data: data,
                timestamp: new Date()
            });

            this.showToast(
                'Report Update',
                `Your report "${data.report_title}" has been updated`
            );
        }

        handleAreaReport(data) {
            this.addNotification({
                type: 'area_report',
                title: 'New Report in Your Area',
                message: `New report: "${data.report_title}" near ${data.location}`,
                data: data,
                timestamp: new Date()
            });

            this.showToast(
                'New Report in Your Area',
                `New report: "${data.report_title}" near ${data.location}`
            );
        }

        handleGroupUpdate(data) {
            this.addNotification({
                type: 'group_update',
                title: 'Security Group Update',
                message: data.message,
                data: data,
                timestamp: new Date()
            });

            this.showToast('Security Group Update', data.message);
        }

        handleNewComment(data) {
            this.addNotification({
                type: 'comment',
                title: 'New Comment',
                message: `New comment on your report "${data.report_title}"`,
                data: data,
                timestamp: new Date()
            });

            this.showToast(
                'New Comment',
                `New comment on your report "${data.report_title}"`
            );
        }

        addNotification(notification) {
            this.notifications.unshift(notification);
            this.unreadCount++;
            this.updateNotificationPanel();
            this.updateNotificationBadge();
        }

        markAsRead(notificationId) {
            $.ajax({
                url: sandcrimeNotifications.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mark_notification_read',
                    nonce: sandcrimeNotifications.nonce,
                    notification_id: notificationId
                },
                success: (response) => {
                    if (response.success) {
                        const notification = this.notifications.find(n => n.id === notificationId);
                        if (notification && !notification.read) {
                            notification.read = true;
                            this.unreadCount--;
                            this.updateNotificationBadge();
                        }
                    }
                }
            });
        }

        clearAllNotifications() {
            $.ajax({
                url: sandcrimeNotifications.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'clear_all_notifications',
                    nonce: sandcrimeNotifications.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.notifications = [];
                        this.unreadCount = 0;
                        this.updateNotificationPanel();
                        this.updateNotificationBadge();
                    }
                }
            });
        }

        toggleNotificationPanel() {
            const $panel = $('#notification-panel');
            $panel.toggleClass('active');

            if ($panel.hasClass('active')) {
                this.markAllAsRead();
            }
        }

        updateNotificationPanel() {
            const $container = $('#notification-list');
            $container.empty();

            this.notifications.forEach(notification => {
                $container.append(this.renderNotificationItem(notification));
            });

            if (this.notifications.length === 0) {
                $container.append(`
                    <div class="no-notifications">
                        <p>No notifications to display</p>
                    </div>
                `);
            }
        }

        renderNotificationItem(notification) {
            return `
                <div class="notification-item ${notification.read ? '' : 'unread'}" 
                     data-id="${notification.id}">
                    <div class="notification-icon">
                        ${this.getNotificationIcon(notification.type)}
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">
                            ${this.formatTimestamp(notification.timestamp)}
                        </div>
                    </div>
                </div>
            `;
        }

        updateNotificationBadge() {
            const $badge = $('#notification-badge');
            if (this.unreadCount > 0) {
                $badge.text(this.unreadCount).show();
            } else {
                $badge.hide();
            }
        }

        showToast(title, message) {
            const $toast = $(`
                <div class="notification-toast">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `);

            $('body').append($toast);

            setTimeout(() => {
                $toast.addClass('show');
                setTimeout(() => {
                    $toast.removeClass('show');
                    setTimeout(() => {
                        $toast.remove();
                    }, 300);
                }, 5000);
            }, 100);
        }

        getNotificationIcon(type) {
            const icons = {
                report_update: '📋',
                area_report: '🗺️',
                group_update: '👥',
                comment: '💬'
            };
            return icons[type] || '📢';
        }

        formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) { // less than 1 minute
                return 'Just now';
            } else if (diff < 3600000) { // less than 1 hour
                const minutes = Math.floor(diff / 60000);
                return `${minutes}m ago`;
            } else if (diff < 86400000) { // less than 1 day
                const hours = Math.floor(diff / 3600000);
                return `${hours}h ago`;
            } else {
                return date.toLocaleDateString();
            }
        }
    }

    // Initialize notification manager when document is ready
    $(document).ready(() => {
        if (typeof Pusher !== 'undefined' && sandcrimeNotifications.websocketKey) {
            window.notificationManager = new NotificationManager();
        }
    });

})(jQuery);