<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add shortcode for the crime reporting form
add_shortcode('sandcrime_report_form', 'sandcrime_render_report_form');

function sandcrime_render_report_form() {
    ob_start();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sandcrime_report_submit'])) {
        handle_crime_report_submission();
    }
    ?>
    <div class="sandcrime-report-form">
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sandcrime_report_submission', 'sandcrime_report_nonce'); ?>
            
            <h3>Report Location</h3>
            <div class="form-group">
                <label for="location">Address or Location Description *</label>
                <input type="text" id="location" name="location" required>
            </div>
            
            <h3>Incident Details</h3>
            <div class="form-group">
                <label for="title">Title/Summary *</label>
                <input type="text" id="title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select Category</option>
                    <option value="theft">Theft</option>
                    <option value="vandalism">Vandalism</option>
                    <option value="suspicious_activity">Suspicious Activity</option>
                    <option value="break_in">Break-in</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="datetime">Date and Time of Incident *</label>
                <input type="datetime-local" id="datetime" name="datetime" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Detailed Description *</label>
                <textarea id="description" name="description" rows="5" required></textarea>
            </div>
            
            <h3>Security Groups Involved</h3>
            <div class="form-group">
                <label>Select all that apply:</label>
                <?php
                global $wpdb;
                $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sandcrime_groups ORDER BY title ASC");
                foreach ($groups as $group): ?>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="security_groups[]" value="<?php echo esc_attr($group->id); ?>">
                            <?php echo esc_html($group->title); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-group">
                <label for="photos">Upload Photos (optional)</label>
                <input type="file" id="photos" name="photos[]" multiple accept="image/*">
                <p class="description">You can upload up to 5 photos. Maximum size per photo: 5MB.</p>
            </div>
            
            <input type="submit" name="sandcrime_report_submit" value="Submit Report" class="button button-primary">
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function handle_crime_report_submission() {
    if (!wp_verify_nonce($_POST['sandcrime_report_nonce'], 'sandcrime_report_submission')) {
        wp_die('Invalid nonce');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_reports';
    
    $photo_urls = [];
    if (!empty($_FILES['photos']['name'][0])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        foreach ($_FILES['photos']['name'] as $key => $value) {
            if ($_FILES['photos']['size'][$key] > 5 * 1024 * 1024) {
                continue; // Skip files larger than 5MB
            }
            
            $file = array(
                'name'     => $_FILES['photos']['name'][$key],
                'type'     => $_FILES['photos']['type'][$key],
                'tmp_name' => $_FILES['photos']['tmp_name'][$key],
                'error'    => $_FILES['photos']['error'][$key],
                'size'     => $_FILES['photos']['size'][$key]
            );
            
            $upload = wp_handle_upload($file, array('test_form' => false));
            
            if (!isset($upload['error'])) {
                $photo_urls[] = $upload['url'];
            }
        }
    }
    
    $security_groups = isset($_POST['security_groups']) ? implode(',', array_map('intval', $_POST['security_groups'])) : '';
    
    $data = array(
        'title' => sanitize_text_field($_POST['title']),
        'description' => sanitize_textarea_field($_POST['description']),
        'category' => sanitize_text_field($_POST['category']),
        'date_time' => sanitize_text_field($_POST['datetime']),
        'location' => sanitize_text_field($_POST['location']),
        'result_status' => 'Pending Review',
        'security_groups' => $security_groups,
        'photo_attachments' => implode(',', $photo_urls)
    );
    
    $result = $wpdb->insert($table_name, $data);
    
    if ($result === false) {
        echo '<div class="notice notice-error"><p>Error submitting report. Please try again.</p></div>';
    } else {
        // Send email notification if enabled
        $settings = get_option('sandcrime_settings');
        if (isset($settings['email_notifications']) && $settings['email_notifications']) {
            $to = $settings['notification_email'];
            $subject = 'New Crime Report Submitted';
            $message = "A new crime report has been submitted:\n\n";
            $message .= "Title: {$data['title']}\n";
            $message .= "Location: {$data['location']}\n";
            $message .= "Category: {$data['category']}\n";
            $message .= "Date/Time: {$data['date_time']}\n";
            $message .= "View in admin: " . admin_url('admin.php?page=sandcrime-reports');
            
            wp_mail($to, $subject, $message);
        }
        
        echo '<div class="notice notice-success"><p>Report submitted successfully! It will be reviewed by our team.</p></div>';
    }
}

// Add CSS for the form
add_action('wp_enqueue_scripts', 'sandcrime_enqueue_form_styles');
function sandcrime_enqueue_form_styles() {
    wp_enqueue_style('sandcrime-form-style', SANDCRIME_PLUGIN_URL . 'assets/css/form-style.css');
}