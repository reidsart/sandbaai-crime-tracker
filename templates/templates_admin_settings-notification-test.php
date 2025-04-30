<?php
if (!defined('ABSPATH')) exit;
?>
<div class="sandcrime-test-notifications">
    <h3><?php _e('Test Notifications', 'sandcrime'); ?></h3>
    
    <div class="test-notification-controls">
        <select id="test-notification-type">
            <option value="email"><?php _e('Email', 'sandcrime'); ?></option>
            <option value="push"><?php _e('Push', 'sandcrime'); ?></option>
            <option value="both"><?php _e('Both', 'sandcrime'); ?></option>
        </select>

        <button type="button" id="test-notification" class="button button-secondary">
            <?php _e('Send Test Notification', 'sandcrime'); ?>
        </button>
    </div>

    <div class="test-results" style="display: none;">
        <h4><?php _e('Test Results', 'sandcrime'); ?></h4>
        <div class="test-output"></div>
    </div>

    <div class="delivery-stats">
        <h4><?php _e('Recent Delivery Statistics', 'sandcrime'); ?></h4>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Type', 'sandcrime'); ?></th>
                    <th><?php _e('Success Rate', 'sandcrime'); ?></th>
                    <th><?php _e('Average Delivery Time', 'sandcrime'); ?></th>
                    <th><?php _e('Failed Attempts', 'sandcrime'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Email', 'sandcrime'); ?></td>
                    <td class="email-success-rate">--</td>
                    <td class="email-delivery-time">--</td>
                    <td class="email-failed">--</td>
                </tr>
                <tr>
                    <td><?php _e('Push', 'sandcrime'); ?></td>
                    <td class="push-success-rate">--</td>
                    <td class="push-delivery-time">--</td>
                    <td class="push-failed">--</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.sandcrime-test-notifications {
    margin-top: 20px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.test-notification-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.test-results {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-left: 4px solid #0073aa;
}

.delivery-stats {
    margin-top: 20px;
}

.delivery-stats table {
    margin-top: 10px;
}

.delivery-stats td,
.delivery-stats th {
    padding: 12px;
}
</style>