(function($) {
    'use strict';

    // Map initialization
    class SandCrimeMap {
        constructor(element, options) {
            this.element = element;
            this.options = Object.assign({
                center: [sandcrimeMapData.defaultLat, sandcrimeMapData.defaultLng],
                zoom: sandcrimeMapData.defaultZoom,
                maxZoom: 18
            }, options);

            this.init();
        }

        init() {
            // Initialize map
            this.map = L.map(this.element, {
                center: this.options.center,
                zoom: this.options.zoom
            });

            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(this.map);

            // Initialize marker clusters
            this.markers = L.markerClusterGroup();
            this.map.addLayer(this.markers);

            // Initialize draw controls if enabled
            if (this.options.enableDraw) {
                this.initializeDrawControls();
            }

            // Initialize coverage areas layer
            this.coverageAreas = L.featureGroup().addTo(this.map);

            // Load initial data if specified
            if (this.options.loadReports) {
                this.loadReports();
            }
            if (this.options.loadCoverageAreas) {
                this.loadCoverageAreas();
            }
        }

        initializeDrawControls() {
            const drawOptions = {
                draw: {
                    marker: this.options.enableMarkers || false,
                    circle: false,
                    circlemarker: false,
                    rectangle: false,
                    polygon: this.options.enablePolygons || false,
                    polyline: false
                }
            };

            this.drawControl = new L.Control.Draw(drawOptions);
            this.map.addControl(this.drawControl);

            this.map.on('draw:created', (e) => {
                const layer = e.layer;
                if (this.options.onShapeCreated) {
                    this.options.onShapeCreated(layer);
                }
            });
        }

        loadReports(filters = {}) {
            $.ajax({
                url: sandcrimeMapData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_map_reports',
                    nonce: sandcrimeMapData.nonce,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        this.displayReports(response.data);
                    }
                }
            });
        }

        displayReports(reports) {
            this.markers.clearLayers();

            reports.forEach(report => {
                if (report.lat && report.lng) {
                    const marker = L.marker([report.lat, report.lng]);
                    
                    marker.bindPopup(`
                        <strong>${report.title}</strong><br>
                        Category: ${report.category}<br>
                        Status: ${report.result_status}<br>
                        Date: ${report.date_time}<br>
                        <a href="#" class="view-report" data-id="${report.id}">View Details</a>
                    `);

                    this.markers.addLayer(marker);
                }
            });
        }

        loadCoverageAreas() {
            $.ajax({
                url: sandcrimeMapData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_coverage_areas',
                    nonce: sandcrimeMapData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayCoverageAreas(response.data);
                    }
                }
            });
        }

        displayCoverageAreas(areas) {
            this.coverageAreas.clearLayers();

            areas.forEach(area => {
                if (area.coverage_geojson) {
                    const coverage = JSON.parse(area.coverage_geojson);
                    const layer = L.geoJSON(coverage, {
                        style: {
                            fillColor: '#3388ff',
                            fillOpacity: 0.2,
                            color: '#3388ff',
                            weight: 2
                        }
                    }).bindPopup(`
                        <strong>${area.title}</strong><br>
                        Security Group Coverage Area
                    `);

                    this.coverageAreas.addLayer(layer);
                }
            });
        }

        setCenter(lat, lng, zoom) {
            this.map.setView([lat, lng], zoom || this.map.getZoom());
        }

        addMarker(lat, lng, options = {}) {
            const marker = L.marker([lat, lng], options);
            this.markers.addLayer(marker);
            return marker;
        }

        clearMarkers() {
            this.markers.clearLayers();
        }
    }

    // Make available globally
    window.SandCrimeMap = SandCrimeMap;

})(jQuery);