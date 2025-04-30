<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap sandcrime-notification-analytics">
    <h1><?php _e('Notification Analytics', 'sandcrime'); ?></h1>

    <div class="analytics-header">
        <div class="time-range-controls">
            <?php foreach (array(
                '24h' => __('24 Hours', 'sandcrime'),
                '7d' => __('7 Days', 'sandcrime'),
                '30d' => __('30 Days', 'sandcrime'),
                '12m' => __('12 Months', 'sandcrime')
            ) as $range => $label): ?>
                <button class="time-range-selector <?php echo $range === '7d' ? 'active' : ''; ?>"
                        data-range="<?php echo esc_attr($range); ?>">
                    <?php echo esc_html($label); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <button id="export-stats" class="button">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Export Stats', 'sandcrime'); ?>
        </button>
    </div>

    <div class="analytics-grid">
        <div class="chart-panel full-width">
            <h3><?php _e('Activity Timeline', 'sandcrime'); ?></h3>
            <canvas id="activity-timeline"></canvas>
        </div>

        <div class="chart-panel">
            <h3><?php _e('Notification Types', 'sandcrime'); ?></h3>
            <canvas id="notification-types"></canvas>
        </div>

        <div class="chart-panel">
            <h3><?php _e('Priority Distribution', 'sandcrime'); ?></h3>
            <canvas id="priority-distribution"></canvas>
        </div>

        <div class="chart-panel">
            <h3><?php _e('Delivery Success Rate', 'sandcrime'); ?></h3>
            <canvas id="delivery-success"></canvas>
        </div>

        <div class="chart-panel">
            <h3><?php _e('User Engagement', 'sandcrime'); ?></h3>
            <canvas id="user-engagement"></canvas>
        </div>
    </div>

    <div class="analytics-summary">
        <div class="summary-card">
            <h4><?php _e('Average Read Time', 'sandcrime'); ?></h4>
            <div class="stat-value" data-stat="avg_read_time">--</div>
        </div>

        <div class="summary-card">
            <h4><?php _e('Open Rate', 'sandcrime'); ?></h4>
            <div class="stat-value" data-stat="open_rate">--</div>
        </div>

        <div class="summary-card">
            <h4><?php _e('Click-through Rate', 'sandcrime'); ?></h4>
            <div class="stat-value" data-stat="click_rate">--</div>
        </div>

        <div class="summary-card">
            <h4><?php _e('Delivery Success Rate', 'sandcrime'); ?></h4>
            <div class="stat-value" data-stat="delivery_rate">--</div>
        </div>
    </div>
</div>