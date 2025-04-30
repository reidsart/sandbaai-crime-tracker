class NotificationDashboard {
    constructor() {
        this.stats = {};
        this.currentPage = 1;
        this.filters = {
            type: '',
            priority: '',
            search: ''
        };

        this.initializeCharts();
        this.initializeEventListeners();
        this.startAutoRefresh();
        this.loadNotificationLog();
    }

    initializeCharts() {
        this.charts = {
            total: new DashboardChart('total', {
                type: 'line',
                height: 50,
                options: {
                    animation: false,
                    plugins: { legend: { display: false } }
                }
            }),
            unread: new DashboardChart('unread', {
                type: 'line',
                height: 50,
                options: {
                    animation: false,
                    plugins: { legend: { display: false } }
                }
            }),
            today: new DashboardChart('bar', {
                type: 'bar',
                height: 50,
                options: {
                    animation: false,
                    plugins: { legend: { display: false } }
                }
            })
        };
    }

    initializeEventListeners() {
        // Quick action buttons
        document.querySelectorAll('.quick-actions button').forEach(button => {
            button.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                this.handleQuickAction(action);
            });
        });

        // Log filters
        document.getElementById('log-type-filter').addEventListener('change', () => {
            this.filters.type = event.target.value;
            this.currentPage = 1;
            this.loadNotificationLog();
        });

        document.getElementById('log-priority-filter').addEventListener('change', () => {
            this.filters.priority = event.target.value;
            this.currentPage = 1;
            this.loadNotificationLog();
        });

        document.getElementById('log-search').addEventListener('input', this.debounce(() => {
            this.filters.search = event.target.value;
            this.currentPage = 1;
            this.loadNotificationLog();
        }, 300));

        // Notification item actions
        document.querySelector('.notification-list').addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]');
            if (action) {
                e.preventDefault();
                this.handleNotificationAction(action.dataset.action, action.dataset.id);
            }
        });
    }

    async handleQuickAction(action) {
        const statusEl = document.querySelector('.action-status');
        const messageEl = statusEl.querySelector('.status-message');
        
        try {
            statusEl.style.display = 'flex';
            messageEl.textContent = this.getActionMessage(action, 'processing');

            const response = await this.makeRequest(action);

            if (response.success) {
                messageEl.textContent = this.getActionMessage(action, 'success');
                this.refreshStats();
                this.loadNotificationLog();
            } else {
                throw new Error(response.data || 'Action failed');
            }
        } catch (error) {
            messageEl.textContent = error.message;
            statusEl.classList.add('error');
        } finally {
            setTimeout(() => {
                statusEl.style.display = 'none';
                statusEl.classList.remove('error');
            }, 3000);
        }
    }

    async handleNotificationAction(action, notificationId) {
        try {
            const response = await this.makeRequest('notification_action', {
                action,
                notification_id: notificationId
            });

            if (response.success) {
                this.refreshStats();
                if (action === 'delete') {
                    this.removeNotificationFromList(notificationId);
                } else {
                    this.updateNotificationInList(notificationId, response.data);
                }
            }
        } catch (error) {
            console.error('Action failed:', error);
            alert(sandcrimeAdmin.i18n.error);
        }
    }

    async loadNotificationLog() {
        const tableBody = document.getElementById('log-entries');
        const paginationEl = document.querySelector('.pagination-links');

        try {
            tableBody.innerHTML = '<tr><td colspan="7" class="loading">Loading...</td></tr>';

            const response = await this.makeRequest('get_notification_log', {
                page: this.currentPage,
                ...this.filters
            });

            if (response.success) {
                this.renderLogEntries(response.data.notifications);
                this.renderPagination(response.data.total, response.data.pages);
            }
        } catch (error) {
            console.error('Failed to load log:', error);
            tableBody.innerHTML = `<tr><td colspan="7" class="error">${sandcrimeAdmin.i18n.error}</td></tr>`;
        }
    }

    renderLogEntries(notifications) {
        const tableBody = document.getElementById('log-entries');
        tableBody.innerHTML = notifications.map(notification => `
            <tr>
                <td>${notification.id}</td>
                <td>${this.escapeHtml(notification.title)}</td>
                <td><span class="type-badge ${notification.type}">${this.escapeHtml(notification.type)}</span></td>
                <td><span class="priority-badge ${notification.priority}">${this.escapeHtml(notification.priority)}</span></td>
                <td>${this.escapeHtml(notification.user_name)}</td>
                <td>${this.formatDate(notification.created_at)}</td>
                <td>${this.getStatusBadge(notification)}</td>
            </tr>
        `).join('');
    }

    renderPagination(total, pages) {
        const paginationEl = document.querySelector('.pagination-links');
        const pageSize = 50;
        
        let html = '';
        
        if (pages > 1) {
            html += `<span class="tablenav-pages-navspan" aria-hidden="true">${total} items</span>`;
            
            if (this.currentPage > 1) {
                html += `
                    <a class="prev-page" href="#" data-page="${this.currentPage - 1}">
                        <span class="screen-reader-text">Previous page</span>
                        <span aria-hidden="true">‹</span>
                    </a>
                `;
            }

            html += `<span class="paging-input">
                <span class="tablenav-paging-text">${this.currentPage} of ${pages}</span>
            </span>`;

            if (this.currentPage < pages) {
                html += `
                    <a class="next-page" href="#" data-page="${this.currentPage + 1}">
                        <span class="screen-reader-text">Next page</span>
                        <span aria-hidden="true">›</span>
                    </a>
                `;
            }
        }

        paginationEl.innerHTML = html;

        // Add event listeners to pagination controls
        paginationEl.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = parseInt(e.target.closest('a').dataset.page);
                this.loadNotificationLog();
            });
        });
    }

    async refreshStats() {
        try {
            const response = await this.makeRequest('get_notification_stats');
            if (response.success) {
                this.updateStats(response.data);
            }
        } catch (error) {
            console.error('Failed to refresh stats:', error);
        }
    }

    updateStats(newStats) {
        this.stats = newStats;
        Object.entries(newStats).forEach(([key, value]) => {
            const el = document.querySelector(`.stat-value[data-type="${key}"]`);
            if (el) {
                el.textContent = new Intl.NumberFormat().format(value);
            }
        });

        this.updateCharts();
    }

    updateCharts() {
        Object.entries(this.charts).forEach(([key, chart]) => {
            chart.update(this.stats[key + '_trend'] || []);
        });
    }

    makeRequest(action, data = {}) {
        return fetch(sandcrimeAdmin.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sandcrime_' + action,
                nonce: sandcrimeAdmin.nonce,
                ...data
            })
        }).then(response => response.json());
    }

    startAutoRefresh() {
        setInterval(() => this.refreshStats(), 30000); // Refresh every 30 seconds
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('default', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    getStatusBadge(notification) {
        if (!notification.read_at) {
            return '<span class="status-badge unread">Unread</span>';
        }
        return '<span class="status-badge read">Read</span>';
    }

    getActionMessage(action, state) {
        const messages = {
            'process-queue': {
                processing: 'Processing notification queue...',
                success: 'Queue processed successfully'
            },
            'retry-failed': {
                processing: 'Retrying failed notifications...',
                success: 'Failed notifications retried successfully'
            },
            'clear-old': {
                processing: 'Clearing old notifications...',
                success: 'Old notifications cleared successfully'
            }
        };

        return messages[action]?.[state] || 'Processing...';
    }
}

// Initialize dashboard when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationDashboard = new NotificationDashboard();
});

// Chart helper class
class DashboardChart {
    constructor(type, config) {
        this.type = type;
        this.chart = new Chart(
            document.querySelector(`.stat-chart[data-type="${type}"]`),
            {
                ...config,
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        borderColor: this.getChartColor(),
                        backgroundColor: this.getChartBackground()
                    }]
                }
            }
        );
    }

    update(data) {
        this.chart.data.labels = data.map(d => d.label);
        this.chart.data.datasets[0].data = data.map(d => d.value);
        this.chart.update();
    }

    getChartColor() {
        const colors = {
            total: '#0d6efd',
            unread: '#dc3545',
            today: '#198754'
        };
        return colors[this.type] || '#6c757d';
    }

    getChartBackground() {
        const color = this.getChartColor();
        return this.type === 'bar' ? color : `${color}20`;
    }
}