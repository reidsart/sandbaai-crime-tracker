class NotificationAnalytics {
    constructor() {
        this.charts = {};
        this.currentFilters = {
            start_date: document.getElementById('start-date').value,
            end_date: document.getElementById('end-date').value,
            delivery_type: document.getElementById('delivery-type-filter').value,
            group_by: document.getElementById('group-by-filter').value
        };

        this.initializeCharts();
        this.bindEvents();
        this.loadData();
    }

    initializeCharts() {
        const currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
        
        switch (currentTab) {
            case 'overview':
                this.initializeOverviewCharts();
                break;
            case 'delivery':
                this.initializeDeliveryCharts();
                break;
            case 'engagement':
                this.initializeEngagementCharts();
                break;
            case 'recipients':
                this.initializeRecipientCharts();
                break;
        }
    }

    bindEvents() {
        document.getElementById('refresh-analytics')
            .addEventListener('click', () => this.refreshData());

        document.getElementById('export-analytics')
            .addEventListener('click', () => this.exportData());

        ['start-date', 'end-date', 'delivery-type-filter', 'group-by-filter'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => {
                this.updateFilters();
            });
        });

        // Responsive chart resize
        window.addEventListener('resize', this.debounce(() => {
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }, 250));
    }

    async loadData() {
        try {
            this.showLoading();
            const data = await this.fetchAnalyticsData();
            this.updateCharts(data);
        } catch (error) {
            this.showError('Failed to load analytics data');
            console.error('Analytics data loading error:', error);
        } finally {
            this.hideLoading();
        }
    }

    async fetchAnalyticsData() {
        const response = await fetch(sandcrimeAnalytics.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'get_notification_analytics',
                nonce: sandcrimeAnalytics.nonce,
                type: this.getCurrentDataType(),
                filters: JSON.stringify(this.currentFilters)
            })
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.data);
        }

        return result.data;
    }

    getCurrentDataType() {
        const currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
        
        switch (currentTab) {
            case 'delivery':
                return 'delivery_stats';
            case 'engagement':
                return 'interaction_rates';
            case 'recipients':
                return 'recipient_engagement';
            default:
                return 'overview';
        }
    }

    updateFilters() {
        this.currentFilters = {
            start_date: document.getElementById('start-date').value,
            end_date: document.getElementById('end-date').value,
            delivery_type: document.getElementById('delivery-type-filter').value,
            group_by: document.getElementById('group-by-filter').value
        };

        this.refreshData();
    }

    async refreshData() {
        await this.loadData();
    }

    async exportData() {
        try {
            const response = await fetch(sandcrimeAnalytics.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'export_notification_analytics',
                    nonce: sandcrimeAnalytics.nonce,
                    type: this.getCurrentDataType(),
                    filters: JSON.stringify(this.currentFilters)
                })
            });

            if (!response.ok) {
                throw new Error('Export failed');
            }

            // Create and trigger download
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = `notification-analytics-${this.getCurrentDataType()}-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();

        } catch (error) {
            this.showError('Failed to export analytics data');
            console.error('Analytics export error:', error);
        }
    }

    initializeOverviewCharts() {
        // Total Deliveries Chart
        this.charts.totalDeliveries = new Chart(
            document.getElementById('total-deliveries-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Total Deliveries',
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        data: []
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );

        // Success Rate Chart
        this.charts.successRate = new Chart(
            document.getElementById('success-rate-chart').getContext('2d'),
            {
                type: 'doughnut',
                data: {
                    labels: ['Successful', 'Failed', 'Pending'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: [
                            '#00a32a',
                            '#d63638',
                            '#dba617'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            }
        );

        // Engagement Chart
        this.charts.engagement = new Chart(
            document.getElementById('engagement-chart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: ['Email', 'Push', 'SMS'],
                    datasets: [{
                        label: 'Engagement Rate (%)',
                        backgroundColor: [
                            'rgba(34, 113, 177, 0.7)',
                            'rgba(0, 163, 42, 0.7)',
                            'rgba(219, 166, 23, 0.7)'
                        ],
                        data: [0, 0, 0]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            }
        );

        // Initialize Top Recipients Table
        this.initializeTopRecipientsTable();
    }

    initializeDeliveryCharts() {
        // Implementation of delivery charts initialization as shown in previous snippets
        // ... (Previous implementation remains the same)
    }

    initializeEngagementCharts() {
        // Implementation of engagement charts initialization as shown in previous snippets
        // ... (Previous implementation remains the same)
    }

    initializeRecipientCharts() {
        // Implementation of recipient charts initialization as shown in previous snippets
        // ... (Previous implementation remains the same)
    }

    updateCharts(data) {
        const currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
        
        switch (currentTab) {
            case 'overview':
                this.updateOverviewCharts(data);
                break;
            case 'delivery':
                this.updateDeliveryCharts(data);
                break;
            case 'engagement':
                this.updateEngagementCharts(data);
                break;
            case 'recipients':
                this.updateRecipientCharts(data);
                break;
        }
    }

    updateOverviewCharts(data) {
        // Implementation of overview charts updates as shown in previous snippets
        // ... (Previous implementation remains the same)
    }

    updateDeliveryCharts(data) {
        // Implementation of delivery charts updates as shown in previous snippets
        // ... (Previous implementation remains the same)
    }

    updateEngagementCharts(data) {
        // Implementation of engagement charts updates as shown in previous snippets
        // ... (Previous implementation remains the same)
    }

    updateRecipientCharts(data) {
        // Implementation of recipient charts updates as shown in previous snippets
        // ... (Previous implementation remains the same)
    }

    processTimeSeriesData(data) {
        const processed = {
            labels: [],
            values: []
        };

        // Sort data by date
        const sortedData = [...data].sort((a, b) => 
            new Date(a.date_group) - new Date(b.date_group)
        );

        // Process data according to group_by setting
        sortedData.forEach(item => {
            processed.labels.push(this.formatDate(item.date_group));
            processed.values.push(parseInt(item.count));
        });

        return processed;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        switch (this.currentFilters.group_by) {
            case 'hour':
                return date.toLocaleString('default', { 
                    hour: 'numeric', 
                    minute: '2-digit' 
                });
            case 'day':
                return date.toLocaleDateString('default', { 
                    month: 'short', 
                    day: 'numeric' 
                });
            case 'week':
                return `Week ${date.getWeek()}`;
            case 'month':
                return date.toLocaleDateString('default', { 
                    month: 'short', 
                    year: 'numeric' 
                });
            default:
                return date.toLocaleDateString();
        }
    }

    showLoading() {
        document.querySelector('.sandcrime-analytics').classList.add('is-loading');
    }

    hideLoading() {
        document.querySelector('.sandcrime-analytics').classList.remove('is-loading');
    }

    showError(message) {
        const notice = document.createElement('div');
        notice.className = 'notice notice-error is-dismissible';
        notice.innerHTML = `<p>${message}</p>`;

        const wrapper = document.querySelector('.wrap');
        wrapper.insertBefore(notice, wrapper.firstChild);

        setTimeout(() => {
            notice.remove();
        }, 5000);
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

// Helper method to get week number
Date.prototype.getWeek = function() {
    const d = new Date(Date.UTC(this.getFullYear(), this.getMonth(), this.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
};

// Initialize analytics when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationAnalytics = new NotificationAnalytics();
});