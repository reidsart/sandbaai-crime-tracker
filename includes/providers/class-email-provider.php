<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Email_Provider implements SandCrime_Provider_Interface {
    private $settings;
    private $mailer;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->initialize_mailer();
    }

    private function initialize_mailer() {
        // Initialize PHPMailer with WordPress defaults
        global $phpmailer;

        if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
            $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);
        }

        $this->mailer = $phpmailer;
        
        // Configure mailer settings
        $this->configure_mailer();
    }

    private function configure_mailer() {
        // Reset any existing settings
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->clearCustomHeaders();

        // Set default sender information
        $this->mailer->setFrom(
            $this->settings->get_setting('email.from_email'),
            $this->settings->get_setting('email.from_name')
        );

        // Set reply-to address if configured
        $reply_to = $this->settings->get_setting('email.reply_to');
        if (!empty($reply_to)) {
            $this->mailer->addReplyTo($reply_to);
        }

        // Set default content type to HTML
        $this->mailer->isHTML(true);

        // Configure SMTP if enabled
        if ($this->settings->get_setting('email.smtp_enabled')) {
            $this->configure_smtp();
        }
    }

    private function configure_smtp() {
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->settings->get_setting('email.smtp_host');
        $this->mailer->Port = $this->settings->get_setting('email.smtp_port');
        
        // Configure encryption
        $encryption = $this->settings->get_setting('email.smtp_encryption');
        if ($encryption === 'tls') {
            $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        // Configure authentication
        if ($this->settings->get_setting('email.smtp_auth')) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->settings->get_setting('email.smtp_username');
            $this->mailer->Password = $this->settings->get_setting('email.smtp_password');
        }
    }

    public function send($notification) {
        try {
            // Reset mailer state
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();

            // Set recipient
            $this->mailer->addAddress($notification['recipient']);

            // Set subject and body
            $this->mailer->Subject = $notification['title'];
            $this->mailer->Body = $this->prepare_html_content($notification['message']);
            $this->mailer->AltBody = $this->prepare_text_content($notification['message']);

            // Add custom headers
            $this->add_custom_headers($notification);

            // Add tracking pixel if enabled
            if ($this->settings->get_setting('email.enable_tracking')) {
                $this->add_tracking_pixel($notification);
            }

            // Add attachments if any
            if (!empty($notification['attachments'])) {
                $this->add_attachments($notification['attachments']);
            }

            // Send the email
            $success = $this->mailer->send();

            // Prepare response
            $response = array(
                'success' => $success,
                'message_id' => $this->mailer->getLastMessageID(),
                'response' => $this->mailer->getLastResponse()
            );

            // Track open rate if enabled
            if ($success && $this->settings->get_setting('email.enable_tracking')) {
                $this->store_tracking_data($notification);
            }

            return $response;

        } catch (Exception $e) {
            throw new Exception(sprintf(
                __('Email sending failed: %s', 'sandcrime'),
                $e->getMessage()
            ));
        }
    }

    private function prepare_html_content($message) {
        // Get email template
        $template = $this->get_email_template();

        // Replace content placeholder with actual message
        return str_replace(
            '{{content}}',
            wpautop($message),
            $template
        );
    }

    private function prepare_text_content($message) {
        // Strip HTML and convert entities
        $text = strip_tags($message);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Convert line breaks
        $text = preg_replace('/\R{2,}/', "\n\n", $text);
        
        return trim($text);
    }

    private function get_email_template() {
        // Get template content from theme or use default
        $template_path = get_stylesheet_directory() . '/sandcrime/email-template.php';
        
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        // Return default template
        return $this->get_default_template();
    }

    private function get_default_template() {
        return '<!DOCTYPE html>
        <html lang="' . get_locale() . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    padding: 20px 0;
                }
                .content {
                    background: #ffffff;
                    padding: 30px;
                    border-radius: 5px;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    font-size: 12px;
                    color: #666;
                }
                @media only screen and (max-width: 600px) {
                    .container {
                        width: 100% !important;
                    }
                    .content {
                        padding: 15px !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    ' . get_bloginfo('name') . '
                </div>
                <div class="content">
                    {{content}}
                </div>
                <div class="footer">
                    ' . get_bloginfo('name') . ' - ' . get_bloginfo('url') . '
                </div>
            </div>
        </body>
        </html>';
    }

    private function add_custom_headers($notification) {
        // Add message ID header
        $this->mailer->addCustomHeader('X-SandCrime-Message-ID', $notification['id']);

        // Add custom headers from notification metadata
        if (!empty($notification['metadata']['headers'])) {
            foreach ($notification['metadata']['headers'] as $name => $value) {
                $this->mailer->addCustomHeader($name, $value);
            }
        }
    }

    private function add_tracking_pixel($notification) {
        $tracking_url = add_query_arg(array(
            'action' => 'sandcrime_track_email',
            'message_id' => $notification['id'],
            'recipient' => base64_encode($notification['recipient']),
            'token' => wp_create_nonce('sandcrime_track_' . $notification['id'])
        ), admin_url('admin-ajax.php'));

        $pixel = '<img src="' . esc_url($tracking_url) . '" alt="" width="1" height="1" style="display:none;">';
        $this->mailer->Body .= $pixel;
    }

    private function add_attachments($attachments) {
        foreach ($attachments as $attachment) {
            if (is_array($attachment)) {
                $this->mailer->addAttachment(
                    $attachment['path'],
                    $attachment['name'] ?? '',
                    $attachment['encoding'] ?? 'base64',
                    $attachment['type'] ?? ''
                );
            } else {
                $this->mailer->addAttachment($attachment);
            }
        }
    }

    private function store_tracking_data($notification) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_email_tracking',
            array(
                'message_id' => $notification['id'],
                'recipient' => $notification['recipient'],
                'sent_at' => current_time('mysql'),
                'status' => 'sent',
                'metadata' => json_encode(array(
                    'subject' => $notification['title'],
                    'template_id' => $notification['template_id']
                ))
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
}