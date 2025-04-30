<?php if (!defined('ABSPATH')) exit; ?>

<div class="coverage-editor-wrapper">
    <div class="editor-header">
        <h2>Edit Coverage Area</h2>
        <div class="drawing-controls">
            <button class="button draw-polygon" id="start-drawing">
                <i class="dashicons dashicons-edit"></i> Draw Area
            </button>
            <button class="button clear-drawing" id="clear-drawing">
                <i class="dashicons dashicons-dismiss"></i> Clear
            </button>
        </div>
    </div>

    <div class="coverage-map-container">
        <div id="coverage-editor-map" class="coverage-map"></div>
        
        <div class="map-controls">
            <div class="control-group">
                <label>Search Location:</label>
                <input type="text" id="location-search" placeholder="Enter address or landmark...">
            </div>
            
            <div class="control-group">
                <label>Coverage Radius:</label>
                <select id="coverage-radius">
                    <option value="500">500m</option>
                    <option value="1000">1km</option>
                    <option value="2000">2km</option>
                    <option value="5000">5km</option>
                    <option value="custom">Custom</option>
                </select>
                <input type="number" id="custom-radius" placeholder="meters" style="display: none;">
            </div>
        </div>

        <div class="coverage-statistics">
            <div class="stat-item">
                <label>Area Coverage:</label>
                <span id="area-coverage">0 km²</span>
            </div>
            <div class="stat-item">
                <label>Perimeter:</label>
                <span id="area-perimeter">0 km</span>
            </div>
            <div class="stat-item">
                <label>Overlapping Groups:</label>
                <span id="overlapping-groups">None</span>
            </div>
        </div>
    </div>

    <div class="editor-sidebar">
        <div class="sidebar-section">
            <h3>Coverage Settings</h3>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="allow-overlap" checked>
                    Allow overlap with other groups
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="auto-assign-reports" checked>
                    Auto-assign reports in this area
                </label>
            </div>
        </div>

        <div class="sidebar-section">
            <h3>Neighboring Groups</h3>
            <div id="neighboring-groups" class="neighboring-groups-list">
                Loading...
            </div>
        </div>

        <div class="editor-actions">
            <button class="button button-primary" id="save-coverage">Save Changes</button>
            <button class="button" id="cancel-editing">Cancel</button>
        </div>
    </div>
</div>

<style>
.coverage-editor-wrapper {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.editor-header {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.drawing-controls {
    display: flex;
    gap: 10px;
}
.coverage-map-container {
    position: relative;
}
.coverage-map {
    height: 600px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.map-controls {
    position: absolute;
    top: 10px;
    left: 10px;
    background: white;
    padding: 10px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 1000;
}
.control-group {
    margin-bottom: 10px;
}
.control-group:last-child {
    margin-bottom: 0;
}
.control-group label {
    display: block;
    margin-bottom: 5px;
    font-size: 12px;
    color: #666;
}
.control-group input,
.control-group select {
    width: 200px;
    padding: 6px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.coverage-statistics {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: white;
    padding: 10px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-item {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 5px;
}
.stat-item:last-child {
    margin-bottom: 0;
}
.stat-item label {
    color: #666;
}
.editor-sidebar {
    background: white;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.sidebar-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}
.sidebar-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}
.form-group {
    margin-bottom: 10px;
}
.form-group:last-child {
    margin-bottom: 0;
}
.neighboring-groups-list {
    max-height: 200px;
    overflow-y: auto;
}
.neighboring-group-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
}
.neighboring-group-item:last-child {
    border-bottom: none;
}
.group-logo {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    overflow: hidden;
}
.group-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.group-info {
    flex: 1;
}
.group-name {
    font-weight: 500;
}
.overlap-status {
    font-size: 12px;
    color: #666;
}
.editor-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}
.editor-actions button {
    flex: 1;
}
@media (max-width: 992px) {
    .coverage-editor-wrapper {
        grid-template-columns: 1fr;
    }
}
</style>