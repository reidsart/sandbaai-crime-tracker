<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .email-wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        .content { margin: 20px 0; }
        .report-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .report-meta {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
        }
        .action-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 15px;
        }
        .map-preview {
            width: 100%;
            max-width: 500px;
            height: 200px;
            margin: 15px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .footer { font-size: 12px; color: #666; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <h2>New Report in Your Area</h2>
        </div>
        
        <div class="content">
            <p>Hello <?php echo esc_html($data['user_name']); ?>,</p>
            
            <p>A new crime report has been submitted in your area:</p>
            
            <div class="report-card">
                <h3><?php echo esc_html($data['report_title']); ?></h3>
                
                <div class="report-meta">
                    <p><strong>Category:</strong> <?php echo esc_html($data['category']); ?></p>
                    <p><strong>Location:</strong> <?php echo esc_html($data['location']); ?></p>
                    <p><strong>Date/Time:</strong> <?php echo esc_html($data['date_time']); ?></p>
                </div>

                <?php if (!empty($data['description'])): ?>
                    <p><strong>Description:</strong></p>
                    <p><?php echo esc_html($data['description']); ?></p>
                <?php endif; ?>

                <?php if (!empty($data['map_image_url'])): ?>
                    <img src="<?php echo esc_url($data['map_image_url']); ?>" 
                         alt="Location Map" class="map-preview">
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url($data['report_url']); ?>" class="action-button">
                View Full Report
            </a>
        </div>

        <div class="footer">
            <p>This is an automated message from Sandbaai Crime Tracker. Please do not reply to this email.</p>
            <p>
                To manage your notification settings or update your area preferences, 
                <a href="<?php echo esc_url($data['settings_url']); ?>">click here</a>.
            </p>
        </div>
    </div>
</body>
</html>