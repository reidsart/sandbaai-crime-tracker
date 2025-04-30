<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Report_Form {
    public static function init() {
        add_shortcode('sandcrime_report_form', array(__CLASS__, 'render_form'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'sandcrime-frontend',
            SANDCRIME_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SANDCRIME_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'sandcrime-report-form',
            SANDCRIME_PLUGIN_URL . 'assets/js/report-form.js',
            array('jquery'),
            SANDCRIME_PLUGIN_VERSION,
            true
        );

        wp_localize_script('sandcrime-report-form', 'sandcrimeForm', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sandcrime_report_nonce'),
            'maxFileSize' => wp_max_upload_size(),
            'allowedTypes' => array('image/jpeg', 'image/png')
        ));
    }

    public static function render_form($atts = array()) {
        $settings = get_option('sandcrime_settings', array());
        
        // Check if public submissions are allowed
        if (empty($settings['enable_public_reports']) && !is_user_logged_in()) {
            return '<div class="sandcrime-notice">Please log in to submit a report.</div>';
        }

        // Get categories
        global $wpdb;
        $categories = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}sandcrime_categories 
            ORDER BY sort_order ASC, name ASC
        ");

        ob_start();
        ?>
        <div class="sandcrime-report-form-wrapper">
            <form id="sandcrime-report-form" class="sandcrime-report-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sandcrime_submit_report', 'sandcrime_report_nonce'); ?>

                <div class="form-row">
                    <label for="report-title">Title *</label>
                    <input type="text" id="report-title" name="title" required 
                           placeholder="Brief description of the incident">
                </div>

                <div class="form-row">
                    <label for="report-category">Category *</label>
                    <select id="report-category" name="category" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category->name); ?>">
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="report-description">Description *</label>
                    <textarea id="report-description" name="description" rows="5" required 
                              placeholder="Provide detailed information about the incident"></textarea>
                </div>

                <div class="form-row">
                    <label for="report-location">Location *</label>
                    <input type="text" id="report-location" name="location" required 
                           placeholder="Street address or location description">
                    <div id="report-map" class="report-map"></div>
                    <input type="hidden" id="report-lat" name="lat">
                    <input type="hidden" id="report-lng" name="lng">
                </div>

                <div class="form-row">
                    <label>Photos (optional)</label>
                    <div class="photo-upload-container">
                        <div class="photo-preview-wrapper">
                            <div id="photo-previews" class="photo-previews"></div>
                        </div>
                        <div class="photo-upload-controls">
                            <button type="button" id="add-photo" class="button">
                                Add Photo
                            </button>
                            <input type="file" id="photo-input" name="photos[]" 
                                   accept="image/jpeg,image/png" multiple style="display: none">
                            <p class="description">
                                Maximum 5 photos. JPG or PNG only. Max size: <?php echo size_format(wp_max_upload_size()); ?> each.
                            </p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($settings['enable_recaptcha']) && !empty($settings['recaptcha_site_key'])): ?>
                    <div class="form-row">
                        <div class="g-recaptcha" 
                             data-sitekey="<?php echo esc_attr($settings['recaptcha_site_key']); ?>">
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <button type="submit" class="button button-primary">Submit Report</button>
                    <div class="submit-status"></div>
                </div>
            </form>
        </div>

        <style>
        .sandcrime-report-form-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-row {
            margin-bottom: 20px;
        }
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-row input[type="text"],
        .form-row select,
        .form-row textarea {
            width: 100%;
            padding: 8px;
        }
        .report-map {
            height: 300px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .photo-previews {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        .photo-preview {
            position: relative;
            padding-bottom: 100%;
            background-size: cover;
            background-position: center;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .photo-preview .remove-photo {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 24px;
            cursor: pointer;
        }
        .submit-status {
            margin-top: 10px;
        }
        .submit-status.error {
            color: #dc3232;
        }
        .submit-status.success {
            color: #46b450;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

// Initialize the report form
SandCrime_Report_Form::init();

// AJAX handler for report submission
add_action('wp_ajax_submit_crime_report', 'handle_report_submission');
add_action('wp_ajax_nopriv_submit_crime_report', 'handle_report_submission');

function handle_report_submission() {
    check_ajax_referer('sandcrime_report_nonce', 'nonce');

    $settings = get_option('sandcrime_settings', array());

    // Validate reCAPTCHA if enabled
    if (!empty($settings['enable_recaptcha']) && !empty($settings['recaptcha_secret_key'])) {
        $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
        
        $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => $settings['recaptcha_secret_key'],
                'response' => $recaptcha_response
            )
        ));

        if (is_wp_error($verify) || empty($verify['body'])) {
            wp_send_json_error('reCAPTCHA verification failed');
            return;
        }

        $verify_response = json_decode($verify['body'], true);
        if (!$verify_response['success']) {
            wp_send_json_error('Please complete the reCAPTCHA verification');
            return;
        }
    }

    // Validate required fields
    $required_fields = array('title', 'category', 'description', 'location');
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error("Missing required field: $field");
            return;
        }
    }

    // Handle file uploads
    $photo_urls = array();
    if (!empty($_FILES['photos'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $files = $_FILES['photos'];
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $_FILES['photo'] = array(
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                );

                $attachment_id = media_handle_upload('photo', 0);
                if (!is_wp_error($attachment_id)) {
                    $photo_urls[] = wp_get_attachment_url($attachment_id);
                }
            }
        }
    }

    // Save report
    global $wpdb;
    $data = array(
        'title' => sanitize_text_field($_POST['title']),
        'category' => sanitize_text_field($_POST['category']),
        'description' => sanitize_textarea_field($_POST['description']),
        'location' => sanitize_text_field($_POST['location']),
        'date_time' => current_time('mysql'),
        'user_id' => get_current_user_id(),
        'user_login' => wp_get_current_user()->user_login,
        'photo_attachments' => !empty($photo_urls) ? implode(',', $photo_urls) : null
    );

    // Add coordinates if provided
    if (!empty($_POST['lat']) && !empty($_POST['lng'])) {
        $wpdb->query($wpdb->prepare(
            "SET @point = ST_PointFromText('POINT(%f %f)')",
            floatval($_POST['lat']),
            floatval($_POST['lng'])
        ));
        $data['coordinates'] = new stdClass();
        $data['coordinates']->expression = '@point';
    }

    $result = $wpdb->insert($wpdb->prefix . 'sandcrime_reports', $data);

    if ($result) {
        $report_id = $wpdb->insert_id;

        // Send notifications
        SandCrime_Utilities::send_notifications('new_report', array_merge(
            $data,
            array('id' => $report_id)
        ));

        wp_send_json_success(array(
            'message' => 'Report submitted successfully',
            'report_id' => $report_id
        ));
    } else {
        wp_send_json_error('Could not save report');
    }
}