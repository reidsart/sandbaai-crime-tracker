<?php
if (!defined('ABSPATH')) exit;
?>
<div class="sandcrime-notification <?php echo esc_attr($priority_class); ?>" data-id="<?php echo esc_attr($notification->id); ?>">
    <div class="notification-icon">
        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
    </div>
    
    <div class="notification-content">
        <div class="notification-header">
            <h4 class="notification-title"><?php echo esc_html($notification->title); ?></h4>
            <span class="notification-timestamp" title="<?php echo esc_attr($notification->created_at); ?>">
                <?php echo esc_html($timestamp); ?>
            </span>
        </div>

        <div class="notification-message">
            <?php echo wp_kses_post($notification->message); ?>
        </div>

        <?php if (!empty($actions)): ?>
            <div class="notification-actions">
                <?php foreach ($actions as $action): ?>
                    <a href="<?php echo esc_url($action['url']); ?>" 
                       class="notification-action <?php echo esc_attr($action['class'] ?? ''); ?>"
                       <?php if (!empty($action['data'])): ?>
                           <?php foreach ($action['data'] as $key => $value): ?>
                               data-<?php echo esc_attr($key); ?>="<?php echo esc_attr($value); ?>"
                           <?php endforeach; ?>
                       <?php endif; ?>>
                        <?php echo esc_html($action['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <button type="button" class="notification-dismiss" aria-label="<?php esc_attr_e('Dismiss notification', 'sandcrime'); ?>">
        <span class="dashicons dashicons-no-alt"></span>
    </button>
</div>