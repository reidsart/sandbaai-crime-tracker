<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .email-wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 15px; border-radius: 5px; }
        .content { margin: 20px 0; }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 14px;
            font-weight: 500;
        }
        .status-pending { background: #f0f0f1; }
        .status-in-progress { background: #e5f5fa; color: #0a4b78; }
        .status-resolved { background: #edfaef; color: #0a7s1b; }
        .status-closed { background: #f1f1f1; color: #666; }
        .action-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 15px;
        }
        .footer { font-size: 12px; color: #666; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <h2>Report Update</h2>
        </div>
        
        <div class="content">
            <p>Hello <?php echo esc_html($data['user_name']); ?>,</p>
            
            <p>There has been an update to your report: <strong><?php echo esc_html($data['report_title']); ?></strong></p>
            
            <div class="update-details">
                <p><strong>Update Type:</strong> <?php echo esc_html($data['update_type']); ?></p>
                
                <?php if (!empty($data['status_change'])): ?>
                    <p>
                        <strong>Status Changed:</strong>
                        <span class="status-badge status-<?php echo sanitize_html_class(strtolower($data['new_status'])); ?>">
                            <?php echo esc_html($data['new_status']); ?>
                        </span>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($data['update_message'])): ?>
                    <p><strong>Message:</strong></p>
                    <p><?php echo esc_html($data['update_message']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($data['assigned_group'])): ?>
                    <p><strong>Assigned to Security Group:</strong> <?php echo esc_html($data['assigned_group']); ?></p>
                <?php endif; ?>
            </div>

            <a href="<?php echo esc_url($data['report_url']); ?>" class="action-button">
                View Report Details
            </a>
        </div>

        <div class="footer">
            <p>This is an automated message from Sandbaai Crime Tracker. Please do not reply to this email.</p>
            <p>
                To manage your notification settings, 
                <a href="<?php echo esc_url($data['settings_url']); ?>">click here</a>.
            </p>
        </div>
    </div>
</body>
</html>