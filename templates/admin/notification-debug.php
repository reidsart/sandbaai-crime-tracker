<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap sandcrime-notification-debug">
    <h1><?php _e('Notification Debug Logs', 'sandcrime'); ?></h1>

    <div class="debug-controls">
        <div class="filters">
            <select id="log-type-filter">
                <option value="all"><?php _e('All Types', 'sandcrime'); ?></option>
                <option value="error"><?php _e('Errors', 'sandcrime'); ?></option>
                <option value="event"><?php _e('Events', 'sandcrime'); ?></option>
                <option value="delivery"><?php _e('Deliveries', 'sandcrime'); ?></option>
            </select>

            <input type="date" 
                   id="log-date-filter" 
                   value="<?php echo date('Y-m-d'); ?>"
                   max="<?php echo date('Y-m-d'); ?>">

            <input type="text" 
                   id="log-search" 
                   placeholder="<?php esc_attr_e('Search logs...', 'sandcrime'); ?>">
        </div>

        <div class="actions">
            <button type="button" id="refresh-logs" class="button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'sandcrime'); ?>
            </button>

            <button type="button" id="clear-logs" class="button button-link-delete">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear Logs', 'sandcrime'); ?>
            </button>

            <button type="button" id="export-logs" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export', 'sandcrime'); ?>
            </button>
        </div>
    </div>

    <div class="debug-content">
        <div id="log-entries"></div>
        <div id="log-details" class="hidden">
            <div class="details-header">
                <h3><?php _e('Log Entry Details', 'sandcrime'); ?></h3>
                <button type="button" class="close-details">×</button>
            </div>
            <div class="details-content"></div>
        </div>
    </div>

    <div id="log-entry-template" style="display: none;">
        <div class="log-entry" data-entry-id="{id}">
            <div class="entry-header">
                <span class="entry-type {type}">{type}</span>
                <span class="entry-time">{time}</span>
                <span class="entry-user">{user}</span>
            </div>
            <div class="entry-content">
                <p class="entry-message">{message}</p>
                <div class="entry-meta">
                    {meta}
                </div>
            </div>
            <div class="entry-actions">
                <button type="button" class="button-link view-details">
                    <?php _e('View Details', 'sandcrime'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.sandcrime-notification-debug {
    max-width: 1400px;
    margin: 20px auto;
}

.debug-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.filters {
    display: flex;
    gap: 10px;
}

.filters select,
.filters input {
    min-width: 200px;
}

.actions {
    display: flex;
    gap: 10px;
}

.debug-content {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}

.debug-content.with-details {
    grid-template-columns: 1fr 400px;
}

#log-entries {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.log-entry {
    padding: 15px;
    border-bottom: 1px solid #f0f0f1;
    transition: background-color 0.2s ease;
}

.log-entry:hover {
    background-color: #f8f9fa;
}

.entry-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.entry-type {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.entry-type.error {
    background-color: #dc3545;
    color: #fff;
}

.entry-type.event {
    background-color: #0d6efd;
    color: #fff;
}

.entry-type.delivery {
    background-color: #198754;
    color: #fff;
}

.entry-time,
.entry-user {
    color: #666;
    font-size: 12px;
}

.entry-message {
    margin: 0 0 10px;
    font-size: 14px;
    line-height: 1.4;
}

.entry-meta {
    font-size: 12px;
    color: #666;
}

.entry-actions {
    margin-top: 10px;
    text-align: right;
}

#log-details {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    height: fit-content;
    position: sticky;
    top: 32px;
}

.details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #f0f0f1;
}

.details-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.close-details {
    border: none;
    background: none;
    cursor: pointer;
    font-size: 20px;
    color: #666;
    padding: 0;
}

.details-content {
    padding: 15px;
}

.stack-trace {
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    background: #f8f9fa;
    padding: 10px;
    border-radius: 3px;
    margin-top: 10px;
}

@media screen and (max-width: 1200px) {
    .debug-controls {
        flex-direction: column;
        gap: 15px;
    }

    .filters {
        width: 100%;
    }

    .actions {
        width: 100%;
        justify-content: flex-end;
    }

    .debug-content.with-details {
        grid-template-columns: 1fr;
    }

    #log-details {
        position: fixed;
        top: 32px;
        right: 0;
        bottom: 0;
        width: 100%;
        max-width: 400px;
        z-index: 100;
        box-shadow: -2px 0 5px rgba(0,0,0,.1);
        display: none;
    }

    #log-details.visible {
        display: block;
    }
}

@media screen and (max-width: 782px) {
    .filters {
        flex-direction: column;
    }

    .filters select,
    .filters input {
        width: 100%;
    }

    .actions {
        flex-wrap: wrap;
        gap: 5px;
    }

    .actions button {
        flex: 1;
    }
}
</style>