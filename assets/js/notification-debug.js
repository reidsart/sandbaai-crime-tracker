class NotificationDebug {
    constructor() {
        this.currentLogId = null;
        this.logTemplate = document.getElementById('log-entry-template').innerHTML;
        
        this.initializeEventListeners();
        this.loadLogs();
        this.startAutoRefresh();
    }

    initializeEventListeners() {
        // Filter changes
        document.getElementById('log-type-filter').addEventListener('change', () => this.loadLogs());
        document.getElementById('log-date-filter').addEventListener('change', () => this.loadLogs());
        document.getElementById('log-search').addEventListener('input', this.debounce(() => this.loadLogs(), 300));

        // Action buttons
        document.getElementById('refresh-logs').addEventListener('click', () => this.loadLogs());
        document.getElementById('clear-logs').addEventListener('click', () => this.clearLogs());
        document.getElementById('export-logs').addEventListener('click', () => this.exportLogs());

        // Log entry interactions
        document.getElementById('log-entries').addEventListener('click', (e) => {
            const viewDetailsBtn = e.target.closest('.view-details');
            if (viewDetailsBtn) {
                const entry = viewDetailsBtn.closest('.log-entry');
                this.showLogDetails(entry.dataset.entryId);
            }
        });

        // Close details panel
        document.querySelector('.close-details').addEventListener('click', () => {
            this.hideLogDetails();
        });

        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideLogDetails();
            }
        });
    }

    async loadLogs() {
        const type = document.getElementById('log-type-filter').value;
        const date = document.getElementById('log-date-filter').value;
        const search = document.getElementById('log-search').value;

        try {
            const response = await this.makeRequest('get_notification_logs', {
                type,
                date,
                search
            });

            if (response.success) {
                this.renderLogs(response.data);
            } else {
                throw new Error(response.data);
            }
        } catch (error) {
            this.showError('Failed to load logs: ' + error.message);
        }
    }

    renderLogs(logs) {
        const container = document.getElementById('log-entries');
        container.innerHTML = '';

        if (logs.length === 0) {
            container.innerHTML = '<div class="no-logs">No logs found</div>';
            return;
        }

        logs.forEach((log, index) => {
            const entry = this.createLogEntry(log, index);
            container.appendChild(entry);
        });
    }

    createLogEntry(log, index) {
        const template = this.logTemplate
            .replace('{id}', index)
            .replace('{type}', log.type)
            .replace('{time}', this.formatDate(log.timestamp))
            .replace('{user}', log.user)
            .replace('{message}', this.escapeHtml(log.message || log.event))
            .replace('{meta}', this.formatMeta(log));

        const div = document.createElement('div');
        div.innerHTML = template;
        return div.firstElementChild;
    }

    formatMeta(log) {
        let meta = '';
        
        if (log.context && Object.keys(log.context).length > 0) {
            meta += '<div class="context">';
            Object.entries(log.context).forEach(([key, value]) => {
                meta += `<div><strong>${this.escapeHtml(key)}:</strong> ${this.escapeHtml(value)}</div>`;
            });
            meta += '</div>';
        }

        return meta;
    }

    showLogDetails(logId) {
        const log = this.currentLogs[logId];
        const detailsPanel = document.getElementById('log-details');
        const content = detailsPanel.querySelector('.details-content');

        content.innerHTML = this.formatLogDetails(log);
        
        document.querySelector('.debug-content').classList.add('with-details');
        detailsPanel.classList.remove('hidden');
        this.currentLogId = logId;
    }

    hideLogDetails() {
        document.querySelector('.debug-content').classList.remove('with-details');
        document.getElementById('log-details').classList.add('hidden');
        this.currentLogId = null;
    }

    formatLogDetails(log) {
        let html = `
            <div class="detail-group">
                <h4>Timestamp</h4>
                <p>${this.formatDate(log.timestamp)}</p>
            </div>
        `;

        if (log.trace) {
            html += `
                <div class="detail-group">
                    <h4>Stack Trace</h4>
                    <div class="stack-trace">
                        ${this.formatStackTrace(log.trace)}
                    </div>
                </div>
            `;
        }

        return html;
    }

    formatStackTrace(trace) {
        return trace.map(frame => {
            return `${frame.file}:${frame.line} - ${frame.function}`;
        }).join('\n');
    }

    async clearLogs() {
        if (!confirm('Are you sure you want to clear all logs?')) {
            return;
        }

        try {
            const response = await this.makeRequest('clear_notification_logs');
            if (response.success) {
                this.showSuccess('Logs cleared successfully');
                this.loadLogs();
            } else {
                throw new Error(response.data);
            }
        } catch (error) {
            this.showError('Failed to clear logs: ' + error.message);
        }
    }

    async exportLogs() {
        const type = document.getElementById('log-type-filter').value;
        const date = document.getElementById('log-date-filter').value;
        const search = document.getElementById('log-search').value;

        const params = new URLSearchParams({
            action: 'export_notification_logs',
            nonce: sandcrimeDebug.nonce,
            type,
            date,
            search
        });

        window.location.href = `${sandcrimeDebug.ajaxUrl}?${params.toString()}`;
    }

    makeRequest(action, data = {}) {
        return fetch(sandcrimeDebug.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sandcrime_' + action,
                nonce: sandcrimeDebug.nonce,
                ...data
            })
        }).then(response => response.json());
    }

    startAutoRefresh() {
        setInterval(() => {
            if (!document.hidden) {
                this.loadLogs();
            }
        }, 30000); // Refresh every 30 seconds
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

        const wrap = document.querySelector('.wrap');
        wrap.insertBefore(notice, wrap.firstChild);

        setTimeout(() => {
            notice.remove();
        }, 5000);
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
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
}

// Initialize debug interface when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationDebug = new NotificationDebug();
});