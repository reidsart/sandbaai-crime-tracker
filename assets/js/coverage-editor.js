(function($) {
    'use strict';

    class CoverageEditor {
        constructor() {
            this.map = null;
            this.drawControl = null;
            this.searchControl = null;
            this.coverageLayer = null;
            this.neighboringLayers = [];
            this.drawnItems = new L.FeatureGroup();
            
            this.initializeMap();
            this.initializeControls();
            this.initializeEventListeners();
            this.loadExistingCoverage();
            this.loadNeighboringGroups();
        }

        initializeMap() {
            this.map = L.map('coverage-editor-map').setView(
                [sandcrimeGroups.defaultLat, sandcrimeGroups.defaultLng], 
                13
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(this.map);

            this.map.addLayer(this.drawnItems);
        }

        initializeControls() {
            // Initialize draw control
            this.drawControl = new L.Control.Draw({
                draw: {
                    polygon: {
                        allowIntersection: false,
                        drawError: {
                            color: '#e1e100',
                            timeout: 1000
                        },
                        shapeOptions: {
                            color: '#3388ff'
                        },
                        showArea: true
                    },
                    circle: false,
                    rectangle: true,
                    circlemarker: false,
                    marker: false,
                    polyline: false
                },
                edit: {
                    featureGroup: this.drawnItems,
                    remove: true
                }
            });
            this.map.addControl(this.drawControl);

            // Initialize search control
            this.searchControl = new L.Control.Search({
                container: 'location-search',
                url: 'https://nominatim.openstreetmap.org/search?format=json&q={s}',
                jsonpParam: 'json_callback',
                propertyName: 'display_name',
                propertyLoc: ['lat', 'lon'],
                marker: false,
                autoCollapse: true,
                autoType: false,
                minLength: 2,
                zoom: 15
            });
            this.map.addControl(this.searchControl);
        }

        initializeEventListeners() {
            // Drawing events
            this.map.on(L.Draw.Event.CREATED, (e) => {
                this.drawnItems.clearLayers();
                this.drawnItems.addLayer(e.layer);
                this.updateCoverageStats();
                this.checkOverlaps();
            });

            this.map.on(L.Draw.Event.EDITED, () => {
                this.updateCoverageStats();
                this.checkOverlaps();
            });

            // Control buttons
            $('#start-drawing').on('click', () => {
                new L.Draw.Polygon(this.map, this.drawControl.options.polygon).enable();
            });

            $('#clear-drawing').on('click', () => {
                this.drawnItems.clearLayers();
                this.updateCoverageStats();
            });

            // Coverage radius changes
            $('#coverage-radius').on('change', (e) => {
                if (e.target.value === 'custom') {
                    $('#custom-radius').show();
                } else {
                    $('#custom-radius').hide();
                    this.updateCircularCoverage(parseInt(e.target.value));
                }
            });

            $('#custom-radius').on('change', (e) => {
                this.updateCircularCoverage(parseInt(e.target.value));
            });

            // Save and cancel actions
            $('#save-coverage').on('click', () => this.saveCoverage());
            $('#cancel-editing').on('click', () => this.cancelEditing());
        }

        loadExistingCoverage() {
            if (sandcrimeGroups.existingCoverage) {
                const coverage = JSON.parse(sandcrimeGroups.existingCoverage);
                this.coverageLayer = L.geoJSON(coverage, {
                    style: {
                        color: '#3388ff',
                        fillOpacity: 0.2
                    }
                });
                this.drawnItems.addLayer(this.coverageLayer);
                this.map.fitBounds(this.coverageLayer.getBounds());
                this.updateCoverageStats();
            }
        }

        loadNeighboringGroups() {
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_neighboring_groups',
                    nonce: sandcrimeGroups.nonce,
                    group_id: sandcrimeGroups.groupId
                },
                success: (response) => {
                    if (response.success) {
                        this.displayNeighboringGroups(response.data);
                    }
                }
            });
        }

        updateCoverageStats() {
            const area = this.calculateArea();
            const perimeter = this.calculatePerimeter();
            
            $('#area-coverage').text(`${(area / 1000000).toFixed(2)} km²`);
            $('#area-perimeter').text(`${(perimeter / 1000).toFixed(2)} km`);
        }

        calculateArea() {
            let area = 0;
            this.drawnItems.eachLayer((layer) => {
                if (layer instanceof L.Polygon) {
                    area += L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]);
                }
            });
            return area;
        }

        calculatePerimeter() {
            let perimeter = 0;
            this.drawnItems.eachLayer((layer) => {
                if (layer instanceof L.Polygon) {
                    const latlngs = layer.getLatLngs()[0];
                    for (let i = 0; i < latlngs.length - 1; i++) {
                        perimeter += latlngs[i].distanceTo(latlngs[i + 1]);
                    }
                    perimeter += latlngs[latlngs.length - 1].distanceTo(latlngs[0]);
                }
            });
            return perimeter;
        }

        updateCircularCoverage(radius) {
            const center = this.map.getCenter();
            this.drawnItems.clearLayers();
            
            const circle = L.circle(center, {
                radius: radius
            });
            
            // Convert circle to polygon for consistency
            const points = 32;
            const polygon = L.polygon(
                this.createCirclePoints(center, radius, points)
            );
            
            this.drawnItems.addLayer(polygon);
            this.updateCoverageStats();
            this.checkOverlaps();
        }

        createCirclePoints(center, radius, points) {
            const coords = [];
            const lat = center.lat;
            const lng = center.lng;
            
            for (let i = 0; i < points; i++) {
                const angle = (i / points) * (Math.PI * 2);
                const dx = Math.cos(angle) * radius;
                const dy = Math.sin(angle) * radius;
                coords.push(this.destinationPoint(lat, lng, dx, dy));
            }
            coords.push(coords[0]); // Close the polygon
            return coords;
        }

        destinationPoint(lat, lng, dx, dy) {
            const R = 6371000; // Earth's radius in meters
            const dLat = dy / R;
            const dLng = dx / (R * Math.cos(Math.PI * lat / 180));
            
            return L.latLng(
                lat + (dLat * 180 / Math.PI),
                lng + (dLng * 180 / Math.PI)
            );
        }

        checkOverlaps() {
            if (!$('#allow-overlap').is(':checked')) {
                const overlaps = this.findOverlappingGroups();
                const $overlapping = $('#overlapping-groups');
                
                if (overlaps.length > 0) {
                    $overlapping.html(
                        overlaps.map(group => group.name).join(', ')
                    );
                    $('#save-coverage').prop('disabled', true);
                } else {
                    $overlapping.text('None');
                    $('#save-coverage').prop('disabled', false);
                }
            }
        }

        findOverlappingGroups() {
            const overlapping = [];
            const currentPolygon = this.drawnItems.toGeoJSON();

            this.neighboringLayers.forEach(layer => {
                if (turf.intersect(currentPolygon, layer.toGeoJSON())) {
                    overlapping.push({
                        id: layer.groupId,
                        name: layer.groupName
                    });
                }
            });

            return overlapping;
        }

        saveCoverage() {
            const coverage = this.drawnItems.toGeoJSON();
            
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'save_group_coverage',
                    nonce: sandcrimeGroups.nonce,
                    group_id: sandcrimeGroups.groupId,
                    coverage: JSON.stringify(coverage),
                    settings: {
                        allow_overlap: $('#allow-overlap').is(':checked'),
                        auto_assign: $('#auto-assign-reports').is(':checked')
                    }
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to save coverage area');
                    }
                }
            });
        }

        cancelEditing() {
            if (confirm('Are you sure you want to cancel? All changes will be lost.')) {
                location.reload();
            }
        }
    }

    // Initialize coverage editor when document is ready
    $(document).ready(() => {
        new CoverageEditor();
    });

})(jQuery);