<?php
if (!defined('ABSPATH')) exit;

$notification_data = json_decode($notification->data, true);
$site_url = get_site_url();
?>

<div class="single-notification">
    <h2><?php echo esc_html($notification->title); ?></h2>

    <div class="notification-meta">
        <span class="notification-type">
            <?php echo esc_html(ucfirst($notification->type)); ?>
        </span>
        <?php if ($notification->priority !== 'normal'): ?>
            <span class="priority-badge priority-<?php echo esc_attr($notification->priority); ?>">
                <?php echo esc_html(ucfirst($notification->priority)); ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="notification-message">
        <?php echo wp_kses_post($notification->message); ?>
    </div>

    <?php if (!empty($notification_data['action_url'])): ?>
        <div class="notification-action">
            <a href="<?php echo esc_url($notification_data['action_url']); ?>" class="button">
                <?php echo esc_html($notification_data['action_text'] ?? 'View Details'); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if (!empty($notification_data['additional_info'])): ?>
        <div class="additional-info">
            <?php foreach ($notification_data['additional_info'] as $label => $value): ?>
                <div class="info-item">
                    <strong><?php echo esc_html($label); ?>:</strong>
                    <?php echo esc_html($value); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.single-notification {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.notification-meta {
    margin: 10px 0;
    color: #666;
    font-size: 14px;
}

.notification-type {
    background: #e3f2fd;
    color: #1976d2;
    padding: 3px 8px;
    border-radius: 4px;
    margin-right: 10px;
}

.priority-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.priority-urgent {
    background: #ffebee;
    color: #c62828;
}

.priority-high {
    background: #fff3e0;
    color: #e65100;
}

.priority-low {
    background: #f5f5f5;
    color: #666;
}

.notification-message {
    margin: 15px 0;
    line-height: 1.6;
}

.notification-action {
    margin: 20px 0;
}

.additional-info {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.info-item {
    margin: 5px 0;
    font-size: 14px;
}
</style>