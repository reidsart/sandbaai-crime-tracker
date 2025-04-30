(function($) {
    'use strict';

    class NotificationCenter {
        constructor() {
            this.offset = 0;
            this.loading = false;
            this.hasMore = true;
            this.currentFilter = 'all';
            this.notifications = [];
            this.unreadCount = 0;

            this.initializeEventListeners();
            this.loadNotifications();
            this.initializeRealTimeUpdates();
        }

        initializeEventListeners() {
            // Filter buttons
            $('.filter-btn').on('click', (e) => {
                const $btn = $(e.currentTarget);
                $('.filter-btn').removeClass('active');
                $btn.addClass('active');
                this.currentFilter = $btn.data('filter');
                this.resetAndReload();
            });

            // Load more
            $('#load-more-notifications').on('click', () => {
                this.loadNotifications();
            });

            // Mark all as read
            $('#mark-all-read').on('click', () => {
                this.markAllAsRead();
            });

            // Settings
            $('#notification-settings-btn').on('click', () => {
                this.showSettingsModal();
            });

            // Settings form submission
            $('#notification-settings-form').on('submit', (e) => {
                e.preventDefault();
                this.saveSettings($(e.currentTarget));
            });

            // Close modal
            $('.close-modal, .cancel-settings').on('click', () => {
                this.closeSettingsModal();
            });

            // Individual notification click
            $(document).on('click', '.notification-item', (e) => {
                const notificationId = $(e.currentTarget).data('id');
                this.handleNotificationClick(notificationId);
            });

            // Quiet hours toggle
            $('input[name="quiet_hours_enabled"]').on('change', (e) => {
                $('.quiet-hours-settings').toggle(e.target.checked);
            });
        }

        loadNotifications() {
            if (this.loading || !this.hasMore) return;

            this.loading = true;
            $('#load-more-notifications').text('Loading...');

            $.ajax({
                url: sandcrimeNotifications.ajaxUrl,
                method: 'GET',
                data: {
                    action: 'get_notifications',
                    nonce: sandcrimeNotifications.nonce,
                    offset: this.offset,
                    filter: this.currentFilter
                },
                success: (response) => {
                    if (response.success) {
                        this.renderNotifications(response.data.notifications);
                        this.offset += response.data.notifications.length;
                        this.hasMore = response.data.has_more;
                        this.updateUnreadCount(response.data.unread_count);

                        if (!this.hasMore) {
                            $('#load-more-notifications').hide();
                        }
                    }
                },
                complete: () => {
                    this.loading = false;
                    $('#load-more-notifications').text('Load More');
                }
            });
        }

        renderNotifications(notifications) {
            const $list = $('#notification-list');
            if (this.offset === 0) {
                $list.empty();
            }

            notifications.forEach(notification => {
                $list.append(this.createNotificationElement(notification));
            });
        }

        createNotificationElement(notification) {
            return `
                <div class="notification-item ${notification.read ? '' : 'unread'}" 
                     data-id="${notification.id}">
                    <div class="notification-content">
                        <div class="notification-icon">
                            ${this.getNotificationIcon(notification.type)}
                        </div>
                        <div class="notification-details">
                            <div class="notification-title">
                                ${notification.title}
                            </div>
                            <div class="notification-message">
                                ${notification.message}
                            </div>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    ${this.formatTimestamp(notification.created_at)}
                                </span>
                                ${notification.category ? `
                                    <span class="notification-category">
                                        ${notification.category}
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        handleNotificationClick(notificationId) {
            const notification = this.findNotification(notificationId);
            if (!notification) return;

            // Mark as read if unread
            if (!notification.read) {
                this.markAsRead(notificationId);
            }

            // Handle navigation based on notification type
            switch (notification.type) {
                case 'report':
                    window.location.href = notification.data.report_url;
                    break;
                case 'group_message':
                    window.location.href = notification.data.message_url;
                    break;
                case 'comment':
                    window.location.href = notification.data.comment_url;
                    break;
                // Add more cases as needed
            }
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
                        $(`.notification-item[data-id="${notificationId}"]`)
                            .removeClass('unread');
                        this.updateUnreadCount(this.unreadCount - 1);
                    }
                }
            });
        }

        markAllAsRead() {
            $.ajax({
                url: sandcrimeNotifications.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mark_all_notifications_read',
                    nonce: sandcrimeNotifications.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('.notification-item').removeClass('unread');
                        this.updateUnreadCount(0);
                    }
                }
            });
        }

        saveSettings($form) {
            const formData = new FormData($form[0]);
            formData.append('action', 'save_notification_settings');
            formData.append('nonce', sandcrimeNotifications.nonce);

            $.ajax({
                url: sandcrimeNotifications.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.closeSettingsModal();
                        this.showSuccessMessage('Settings saved successfully');
                    } else {
                        this.showErrorMessage('Failed to save settings');
                    }
                }
            });
        }

        initializeRealTimeUpdates() {
            if (typeof Pusher === 'undefined') return;

            const channel = window.notificationManager.channel;

            channel.bind('notification.new', (data) => {
                this.handleNewNotification(data);
            });
        }

        handleNewNotification(data) {
            // Prepend new notification to list
            $('#notification-list').prepend(
                this.createNotificationElement(data)
            );

            // Update unread count
            this.updateUnreadCount(this.unreadCount + 1);

            // Show toast notification
            this.showToast(data.title, data.message);
        }

        showSettingsModal() {
            $('#notification-settings-modal').show();
        }

        closeSettingsModal() {
            $('#notification-settings-modal').hide();
        }

        resetAndReload() {
            this.offset = 0;
            this.hasMore = true;
            $('#load-more-notifications').show();
            this.loadNotifications();
        }

        updateUnreadCount(count) {
            this.unreadCount = count;
            const $badge = $('#notification-count');
            
            if (count > 0) {
                $badge.text(count).show();
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
                report: '<i class="dashicons dashicons-clipboard"></i>',
                group_message: '<i class="dashicons dashicons-groups"></i>',
                comment: '<i class="dashicons dashicons-admin-comments"></i>',
                alert: '<i class="dashicons dashicons-warning"></i>',
                update: '<i class="dashicons dashicons-update"></i>'
            };
            return icons[type] || '<i class="dashicons dashicons-bell"></i>';
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
            } else if (diff < 604800000) { // less than 1 week
                const days = Math.floor(diff / 86400000);
                return `${days}d ago`;
            } else {
                return date.toLocaleDateString();
            }
        }

        findNotification(id) {
            return this.notifications.find(n => n.id === id);
        }

        showSuccessMessage(message) {
            this.showToast('Success', message);
        }

        showErrorMessage(message) {
            this.showToast('Error', message);
        }
    }

    // Initialize notification center when document is ready
    $(document).ready(() => {
        if ($('#notification-center').length) {
            window.notificationCenter = new NotificationCenter();
        }
    });

})(jQuery);