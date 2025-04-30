<?php
if (!defined('ABSPATH')) exit;

$tabs = array(
    'overview' => __('Overview', 'sandcrime'),
    'delivery' => __('Delivery Stats', 'sandcrime'),
    'engagement' => __('Engagement', 'sandcrime'),
    'recipients' => __('Recipients', 'sandcrime')
);
?>

<div class="wrap sandcrime-analytics">
    <h1><?php _e('Notification Analytics', 'sandcrime'); ?></h1>

    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_label): ?>
            <a href="?page=sandcrime-notification-analytics&tab=<?php echo esc_attr($tab_key); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="analytics-filters">
        <div class="date-range">
            <label for="start-date"><?php _e('From:', 'sandcrime'); ?></label>
            <input type="date" 
                   id="start-date" 
                   value="<?php echo esc_attr($start_date); ?>" 
                   max="<?php echo esc_attr(date('Y-m-d')); ?>">

            <label for="end-date"><?php _e('To:', 'sandcrime'); ?></label>
            <input type="date" 
                   id="end-date" 
                   value="<?php echo esc_attr($end_date); ?>" 
                   max="<?php echo esc_attr(date('Y-m-d')); ?>">
        </div>

        <div class="filter-controls">
            <select id="delivery-type-filter">
                <option value=""><?php _e('All Types', 'sandcrime'); ?></option>
                <option value="email"><?php _e('Email', 'sandcrime'); ?></option>
                <option value="push"><?php _e('Push', 'sandcrime'); ?></option>
                <option value="sms"><?php _e('SMS', 'sandcrime'); ?></option>
            </select>

            <select id="group-by-filter">
                <option value="day"><?php _e('Daily', 'sandcrime'); ?></option>
                <option value="week"><?php _e('Weekly', 'sandcrime'); ?></option>
                <option value="month"><?php _e('Monthly', 'sandcrime'); ?></option>
            </select>

            <button type="button" class="button" id="refresh-analytics">
                <?php _e('Update', 'sandcrime'); ?>
            </button>

            <button type="button" class="button" id="export-analytics">
                <?php _e('Export', 'sandcrime'); ?>
            </button>
        </div>
    </div>

    <?php if ($current_tab === 'overview'): ?>
        <div class="analytics-grid">
            <div class="analytics-card">
                <h3><?php _e('Total Deliveries', 'sandcrime'); ?></h3>
                <div class="analytics-chart" id="total-deliveries-chart"></div>
            </div>

            <div class="analytics-card">
                <h3><?php _e('Delivery Success Rate', 'sandcrime'); ?></h3>
                <div class="analytics-chart" id="success-rate-chart"></div>
            </div>

            <div class="analytics-card">
                <h3><?php _e('Engagement by Type', 'sandcrime'); ?></h3>
                <div class="analytics-chart" id="engagement-chart"></div>
            </div>

            <div class="analytics-card">
                <h3><?php _e('Top Recipients', 'sandcrime'); ?></h3>
                <div class="analytics-table" id="top-recipients-table"></div>
            </div>
        </div>

    <?php elseif ($current_tab === 'delivery'): ?>
        <div class="analytics-section">
            <div class="analytics-card full-width">
                <h3><?php _e('Delivery Trends', 'sandcrime'); ?></h3>
                <div class="analytics-chart" id="delivery-trends-chart"></div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3><?php _e('Delivery Status Distribution', 'sandcrime'); ?></h3>
                    <div class="analytics-chart" id="status-distribution-chart"></div>
                </div>

                <div class="analytics-card">
                    <h3><?php _e('Delivery Type Comparison', 'sandcrime'); ?></h3>
                    <div class="analytics-chart" id="type-comparison-chart"></div>
                </div>
            </div>
        </div>

    <?php elseif ($current_tab === 'engagement'): ?>
        <div class="analytics-section">
            <div class="analytics-card full-width">
                <h3><?php _e('Engagement Timeline', 'sandcrime'); ?></h3>
                <div class="analytics-chart" id="engagement-timeline-chart"></div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3><?php _e('Interaction Types', 'sandcrime'); ?></h3>
                    <div class="analytics-chart" id="interaction-types-chart"></div>
                </div>

                <div class="analytics-card">
                    <h3><?php _e('Response Time Distribution', 'sandcrime'); ?></h3>
                    <div class="analytics-chart" id="response-time-chart"></div>
                </div>
            </div>
        </div>

    <?php elseif ($current_tab === 'recipients'): ?>
        <div class="analytics-section">
            <div class="analytics-card full-width">
                <h3><?php _e('Recipient Engagement Analysis', 'sandcrime'); ?></h3>
                <div class="analytics-table" id="recipient-analysis-table"></div>
            </div>

            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3><?php _e('Delivery Preferences', 'sandcrime'); ?></h3>
                    <div class="analytics-chart" id="preferences-chart"></div>
                </div>

                <div class="analytics-card">
                    <h3><?php _e('Engagement Heat Map', 'sandcrime'); ?></h3>
                    <div class="analytics-chart" id="engagement-heatmap"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>