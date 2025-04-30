class NotificationsUI {
    constructor() {
        this.container = document.querySelector('.sandcrime-notifications-list');
        this.pageSize = 10;
        this.currentPage = 1;
        this.isLoading = false;
        this.hasMore = true;
        this.lastNotificationId = null;
        this.refreshInterval = null;

        this.initializeEventListeners();
        this.startAutoRefresh();
    }

    initializeEventListeners() {
        if (!this.container) return;

        // Load more button
        const loadMoreBtn = this.container.querySelector('.load-more-notifications');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', () => this.loadMore());
        }

        // Dismiss notifications
        this.container.addEventListener('click', (e) => {
            const dismissBtn = e.target.closest('.notification-dismiss');
            if (dismissBtn) {
                const notification = dismissBtn.closest('.sandcrime-notification');
                if (notification) {
                    this.dismissNotification(notification.dataset.id);
                }
            }
        });

        // Handle action buttons
        this.container.addEventListener('click', (e) => {
            const actionBtn = e.target.closest('.notification-action');
            if (actionBtn && actionBtn.classList.contains('acknowledge-alert')) {
                e.preventDefault();
                const alertId = actionBtn.dataset.alertId;
                if (alertId) {
                    this.acknowledgeAlert(alertId);
                }
            }
        });

        // Infinite scroll
        this.setupInfiniteScroll();
    }

    startAutoRefresh() {
        const refreshInterval = parseInt(this.container.dataset.autoRefresh, 10) || 60;
        this.refreshInterval = setInterval(() => this.checkNewNotifications(), refreshInterval * 1000);
    }

    async checkNewNotifications() {
        if (!this.lastNotificationId) {
            const firstNotification = this.container.querySelector('.sandcrime-notification');
            if (firstNotification) {
                this.lastNotificationId = firstNotification.dataset.id;
            }
        }

        try {
            const response = await fetch(`${sandcrimeConfig.ajaxUrl}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_new_notifications',
                    nonce: sandcrimeConfig.nonce,
                    last_id: this.lastNotificationId
                })
            });

            const data = await response.json();
            if (data.success && data.data.notifications.length > 0) {
                this.prependNewNotifications(data.data.notifications);
            }
        } catch (error) {
            console.error('Failed to check for new notifications:', error);
        }
    }

    prependNewNotifications(notifications) {
        notifications.reverse().forEach(notification => {
            const notificationElement = this.createNotificationElement(notification);
            notificationElement.classList.add('notification-enter');
            
            this.container.insertBefore(
                notificationElement, 
                this.container.firstChild
            );

            // Trigger animation
            requestAnimationFrame(() => {
                notificationElement.classList.add('notification-enter-active');
                notificationElement.addEventListener('transitionend', () => {
                    notificationElement.classList.remove('notification-enter', 'notification-enter-active');
                }, { once: true });
            });
        });

        this.lastNotificationId = notifications[0].id;
        this.updateNoNotificationsMessage();
    }

    async loadMore() {
        if (this.isLoading || !this.hasMore) return;

        this.isLoading = true;
        const loadMoreBtn = this.container.querySelector('.load-more-notifications');
        if (loadMoreBtn) {
            loadMoreBtn.textContent = sandcrimeConfig.i18n.loading;
            loadMoreBtn.disabled = true;
        }

        try {
            const lastNotification = this.container.querySelector('.sandcrime-notification:last-child');
            const lastId = lastNotification ? lastNotification.dataset.id : null;

            const response = await fetch(`${sandcrimeConfig.ajaxUrl}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_more_notifications',
                    nonce: sandcrimeConfig.nonce,
                    last_id: lastId,
                    page_size: this.pageSize
                })
            });

            const data = await response.json();
            if (data.success) {
                this.appendNotifications(data.data.notifications);
                this.hasMore = data.data.has_more;
                
                if (!this.hasMore && loadMoreBtn) {
                    loadMoreBtn.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Failed to load more notifications:', error);
        } finally {
            this.isLoading = false;
            if (loadMoreBtn) {
                loadMoreBtn.textContent = sandcrimeConfig.i18n.loadMore;
                loadMoreBtn.disabled = false;
            }
        }
    }

    appendNotifications(notifications) {
        const fragment = document.createDocumentFragment();
        
        notifications.forEach(notification => {
            const notificationElement = this.createNotificationElement(notification);
            fragment.appendChild(notificationElement);
        });

        const loadMoreWrapper = this.container.querySelector('.load-more-wrapper');
        if (loadMoreWrapper) {
            this.container.insertBefore(fragment, loadMoreWrapper);
        } else {
            this.container.appendChild(fragment);
        }

        this.updateNoNotificationsMessage();
    }

    async dismissNotification(notificationId) {
        const notification = this.container.querySelector(
            `.sandcrime-notification[data-id="${notificationId}"]`
        );

        if (!notification) return;

        try {
            const response = await fetch(`${sandcrimeConfig.ajaxUrl}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'dismiss_notification',
                    nonce: sandcrimeConfig.nonce,
                    notification_id: notificationId
                })
            });

            const data = await response.json();
            if (data.success) {
                notification.classList.add('notification-exit');
                requestAnimationFrame(() => {
                    notification.classList.add('notification-exit-active');
                    notification.addEventListener('transitionend', () => {
                        notification.remove();
                        this.updateNoNotificationsMessage();
                    }, { once: true });
                });
            }
        } catch (error) {
            console.error('Failed to dismiss notification:', error);
        }
    }

    async acknowledgeAlert(alertId) {
        try {
            const response = await fetch(`${sandcrimeConfig.ajaxUrl}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'acknowledge_alert',
                    nonce: sandcrimeConfig.nonce,
                    alert_id: alertId
                })
            });

            const data = await response.json();
            if (data.success) {
                const notification = this.container.querySelector(
                    `.notification-action[data-alert-id="${alertId}"]`
                ).closest('.sandcrime-notification');
                
                if (notification) {
                    this.dismissNotification(notification.dataset.id);
                }
            }
        } catch (error) {
            console.error('Failed to acknowledge alert:', error);
        }
    }

    setupInfiniteScroll() {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.isLoading && this.hasMore) {
                        this.loadMore();
                    }
                });
            },
            { threshold: 0.5 }
        );

        const loadMoreWrapper = this.container.querySelector('.load-more-wrapper');
        if (loadMoreWrapper) {
            observer.observe(loadMoreWrapper);
        }
    }

    updateNoNotificationsMessage() {
        const hasNotifications = this.container.querySelector('.sandcrime-notification');
        const noNotificationsEl = this.container.querySelector('.no-notifications');

        if (!hasNotifications && !noNotificationsEl) {
            const message = document.createElement('div');
            message.className = 'no-notifications';
            message.innerHTML = `
                <span class="dashicons dashicons-bell"></span>
                <p>${sandcrimeConfig.i18n.noNotifications}</p>
            `;
            this.container.appendChild(message);
        } else if (hasNotifications && noNotificationsEl) {
            noNotificationsEl.remove();
        }
    }

    createNotificationElement(notification) {
        const template = document.createElement('template');
        template.innerHTML = notification.html.trim();
        return template.content.firstChild;
    }
}

// Initialize when document is ready
$(document).ready(() => {
    window.notificationsUI = new NotificationsUI();
});