<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Email styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .email-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .email-content {
            padding: 20px 0;
        }
        .email-footer {
            padding: 20px 0;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 0;
        }
        .notification-item {
            padding: 15px;
            margin: 10px 0;
            background: #f8f9fa;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h1><?php echo get_option('blogname'); ?></h1>
        </div>
        
        <div class="email-content">
            <?php echo $content; ?>
        </div>
        
        <div class="email-footer">
            <p>
                You received this email because you're subscribed to notifications from <?php echo get_option('blogname'); ?>.
                <a href="<?php echo esc_url(home_url('/notification-settings')); ?>">Manage your notification settings</a>
            </p>
        </div>
    </div>
</body>
</html>