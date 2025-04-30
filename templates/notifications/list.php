<?php
if (!defined('ABSPATH')) exit;
?>
<div class="sandcrime-notifications-list" data-auto-refresh="60">
    <?php if (empty($notifications)): ?>
        <div class="no-notifications">
            <span class="dashicons dashicons-bell"></span>
            <p><?php _e('No notifications to display', 'sandcrime'); ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
            <?php echo $renderer->render_notification($notification); ?>
        <?php endforeach; ?>
        
        <?php if (count($notifications) >= 10): ?>
            <div class="load-more-wrapper">
                <button type="button" class="load-more-notifications">
                    <?php _e('Load More', 'sandcrime'); ?>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>