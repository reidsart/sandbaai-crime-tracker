<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Offline - SandCrime Notifications', 'sandcrime'); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .offline-container {
            max-width: 600px;
            padding: 40px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .offline-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
        }

        h1 {
            color: #1a1a1a;
            font-size: 24px;
            margin-bottom: 16px;
        }

        p {
            color: #666;
            margin-bottom: 24px;
        }

        .retry-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0d6efd;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .retry-button:hover {
            background-color: #0b5ed7;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a;
            }

            .offline-container {
                background-color: #2d2d2d;
            }

            h1 {
                color: #fff;
            }

            p {
                color: #ccc;
            }
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <img src="<?php echo plugins_url('assets/images/offline-icon.svg', SANDCRIME_PLUGIN_FILE); ?>" 
             alt="Offline" 
             class="offline-icon">
        
        <h1><?php _e('You\'re Offline', 'sandcrime'); ?></h1>
        
        <p><?php _e('It looks like you\'ve lost your internet connection. Please check your connection and try again.', 'sandcrime'); ?></p>
        
        <a href="javascript:window.location.reload()" class="retry-button">
            <?php _e('Try Again', 'sandcrime'); ?>
        </a>
    </div>
</body>
</html>