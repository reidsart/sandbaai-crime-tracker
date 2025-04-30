class NotificationCharts {
    constructor() {
        this.charts = {};
        this.timeRanges = {
            '24h': { label: 'Last 24 Hours', interval: 'hour' },
            '7d': { label: 'Last 7 Days', interval: 'day' },
            '30d': { label: 'Last 30 Days', interval: 'day' },
            '12m': { label: 'Last 12 Months', interval: 'month' }
        };

        this.currentRange = '7d';
        this.initializeCharts();
        this.initializeEventListeners();
        this.loadData();
    }

    initializeCharts() {
        // Activity Timeline
        this.charts.timeline = new Chart(
            document.getElementById('activity-timeline'),
            this.getTimelineConfig()
        );

        // Notification Types Distribution
        this.charts.types = new Chart(
            document.getElementById('notification-types'),
            this.getTypesConfig()
        );

        // Priority Distribution
        this.charts.priorities = new Chart(
            document.getElementById('priority-distribution'),
            this.getPrioritiesConfig()
        );

        // Delivery Success Rate
        this.charts.delivery = new Chart(
            document.getElementById('delivery-success'),
            this.getDeliveryConfig()
        );

        // User Engagement
        this.charts.engagement = new Chart(
            document.getElementById('user-engagement'),
            this.getEngagementConfig()
        );
    }

    getTimelineConfig() {
        return {
            type: 'line',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        },
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Notifications'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                return `${context.dataset.label}: ${context.parsed.y}`;
                            }
                        }
                    }
                }
            },
            data: {
                datasets: [
                    {
                        label: 'Total',
                        borderColor: '#0d6efd',
                        backgroundColor: '#0d6efd20',
                        fill: true
                    },
                    {
                        label: 'Read',
                        borderColor: '#198754',
                        backgroundColor: '#19875420',
                        fill: true
                    }
                ]
            }
        };
    }

    getTypesConfig() {
        return {
            type: 'doughnut',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const percentage = (context.parsed * 100).toFixed(1);
                                return `${context.label}: ${percentage}%`;
                            }
                        }
                    }
                }
            },
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#0d6efd',
                        '#dc3545',
                        '#ffc107',
                        '#198754',
                        '#6c757d'
                    ]
                }]
            }
        };
    }

    getPrioritiesConfig() {
        return {
            type: 'bar',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            },
            data: {
                labels: ['Urgent', 'High', 'Normal', 'Low'],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#dc3545',
                        '#fd7e14',
                        '#0d6efd',
                        '#6c757d'
                    ]
                }]
            }
        };
    }

    getDeliveryConfig() {
        return {
            type: 'bar',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Success Rate (%)'
                        }
                    }
                }
            },
            data: {
                labels: ['Email', 'Push', 'SMS'],
                datasets: [{
                    label: 'Success Rate',
                    data: [],
                    backgroundColor: '#198754'
                }]
            }
        };
    }

    getEngagementConfig() {
        return {
            type: 'line',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Average Time to Read (minutes)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            },
            data: {
                labels: [],
                datasets: [{
                    label: 'Time to Read',
                    borderColor: '#0d6efd',
                    backgroundColor: '#0d6efd20',
                    fill: true,
                    tension: 0.4
                }]
            }
        };
    }

    async loadData() {
        try {
            const response = await fetch(`${sandcrimeAdmin.ajaxUrl}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_notification_stats',
                    nonce: sandcrimeAdmin.nonce,
                    range: this.currentRange
                })
            });

            const data = await response.json();
            if (data.success) {
                this.updateCharts(data.data);
            }
        } catch (error) {
            console.error('Failed to load chart data:', error);
        }
    }

    updateCharts(data) {
        // Update Timeline
        this.charts.timeline.data.labels = data.timeline.labels;
        this.charts.timeline.data.datasets[0].data = data.timeline.total;
        this.charts.timeline.data.datasets[1].data = data.timeline.read;
        this.charts.timeline.update();

        // Update Types Distribution
        this.charts.types.data.labels = data.types.labels;
        this.charts.types.data.datasets[0].data = data.types.values;
        this.charts.types.update();

        // Update Priorities
        this.charts.priorities.data.datasets[0].data = data.priorities;
        this.charts.priorities.update();

        // Update Delivery Success
        this.charts.delivery.data.datasets[0].data = data.delivery;
        this.charts.delivery.update();

        // Update Engagement
        this.charts.engagement.data.labels = data.engagement.labels;
        this.charts.engagement.data.datasets[0].data = data.engagement.values;
        this.charts.engagement.update();

        this.updateSummaryStats(data.summary);
    }

    updateSummaryStats(summary) {
        Object.entries(summary).forEach(([key, value]) => {
            const el = document.querySelector(`.stat-value[data-stat="${key}"]`);
            if (el) {
                if (key.includes('rate')) {
                    el.textContent = `${value.toFixed(1)}%`;
                } else if (key.includes('time')) {
                    el.textContent = this.formatDuration(value);
                } else {
                    el.textContent = new Intl.NumberFormat().format(value);
                }
            }
        });
    }

    formatDuration(minutes) {
        if (minutes < 60) {
            return `${minutes}m`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}h ${remainingMinutes}m`;
    }

    initializeEventListeners() {
        document.querySelectorAll('.time-range-selector').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const range = e.target.dataset.range;
                if (this.timeRanges[range]) {
                    this.currentRange = range;
                    this.loadData();
                    
                    // Update active state
                    document.querySelectorAll('.time-range-selector').forEach(btn => {
                        btn.classList.toggle('active', btn === e.target);
                    });
                }
            });
        });

        // Add export functionality
        document.getElementById('export-stats').addEventListener('click', () => {
            this.exportStats();
        });
    }

    async exportStats() {
        try {
            const response = await fetch(`${sandcrimeAdmin.ajaxUrl}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'export_notification_stats',
                    nonce: sandcrimeAdmin.nonce,
                    range: this.currentRange
                })
            });

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `notification-stats-${this.currentRange}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Failed to export stats:', error);
            alert(sandcrimeAdmin.i18n.exportError);
        }
    }
}

// Initialize charts when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationCharts = new NotificationCharts();
});