<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($site_name); ?> - Notification Digest</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .notification {
            padding: 10px;
            margin-bottom: 10px;
            background: white;
            border-left: 4px solid #2196F3;
        }
        .notification.urgent {
            border-left-color: #f44336;
        }
        .notification-meta {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo esc_html($site_name); ?></h1>
        <p>Your <?php echo esc_html(ucfirst($period)); ?> Notification Summary</p>
    </div>

    <?php foreach ($notifications as $type => $items): ?>
        <div class="section">
            <h2><?php echo esc_html($this->get_type_label($type)); ?></h2>
            
            <?php foreach ($items as $notification): ?>
                <div class="notification <?php echo esc_attr($notification->priority); ?>">
                    <h3><?php echo esc_html($notification->title); ?></h3>
                    <p><?php echo wp_kses_post($notification->message); ?></p>
                    <div class="notification-meta">
                        <?php echo esc_html(human_time_diff(strtotime($notification->created_at), current_time('timestamp'))); ?> ago
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="footer">
        <p>
            You're receiving this digest because you've subscribed to <?php echo esc_html($period); ?> notifications.
            <br>
            <a href="<?php echo esc_url($site_url . '/notification-settings/'); ?>">Manage your notification preferences</a>
        </p>
    </div>
</body>
</html>