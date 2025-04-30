(function($) {
    'use strict';

    // Chart color palette
    const colors = {
        primary: '#2271b1',
        secondary: '#72aee6',
        success: '#00a32a',
        warning: '#dba617',
        danger: '#d63638',
        light: '#f0f0f1',
        dark: '#1d2327'
    };

    // Initialize charts when document is ready
    $(document).ready(function() {
        initTrendsChart();
        initCategoriesChart();
        initResponseTimesChart();
        initHeatmap();
    });

    // Monthly Trends Chart
    function initTrendsChart() {
        if (!window.trendsData || !document.getElementById('trendsChart')) {
            return;
        }

        const ctx = document.getElementById('trendsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendsData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('default', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Number of Reports',
                    data: trendsData.map(item => item.count),
                    borderColor: colors.primary,
                    backgroundColor: colors.secondary + '40',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }

    // Categories Distribution Chart
    function initCategoriesChart() {
        if (!window.categoriesData || !document.getElementById('categoriesChart')) {
            return;
        }

        const ctx = document.getElementById('categoriesChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categoriesData.map(item => item.category),
                datasets: [{
                    data: categoriesData.map(item => item.count),
                    backgroundColor: [
                        colors.primary,
                        colors.secondary,
                        colors.success,
                        colors.warning,
                        colors.danger,
                        ...generateColors(categoriesData.length - 5)
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }

    // Response Times Chart
    function initResponseTimesChart() {
        if (!window.responseTimesData || !document.getElementById('responseTimesChart')) {
            return;
        }

        const ctx = document.getElementById('responseTimesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: responseTimesData.map(item => item.category),
                datasets: [{
                    label: 'Average Hours',
                    data: responseTimesData.map(item => Math.round(item.avg_hours * 10) / 10),
                    backgroundColor: colors.primary
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                }
            }
        });
    }

    // Crime Heatmap
    function initHeatmap() {
        if (!document.getElementById('crimeHeatmap')) {
            return;
        }

        const map = new SandCrimeMap('crimeHeatmap', {
            center: [sandcrimeMapData.defaultLat, sandcrimeMapData.defaultLng],
            zoom: 13
        });

        // Load heatmap data
        $.ajax({
            url: sandcrimeStats.ajaxUrl,
            method: 'POST',
            data: {
                action: 'get_heatmap_data',
                nonce: sandcrimeStats.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const heatData = response.data.map(point => ([
                        point.lat,
                        point.lng,
                        point.weight
                    ]));

                    const heatLayer = L.heatLayer(heatData, {
                        radius: 25,
                        blur: 15,
                        maxZoom: 15,
                        gradient: {
                            0.4: '#ffffb2',
                            0.6: '#fd8d3c',
                            0.8: '#f03b20',
                            1.0: '#bd0026'
                        }
                    }).addTo(map.map);
                }
            }
        });
    }

    // Helper function to generate additional colors
    function generateColors(count) {
        const colors = [];
        for (let i = 0; i < count; i++) {
            colors.push(`hsl(${(i * 360) / count}, 70%, 50%)`);
        }
        return colors;
    }

})(jQuery);