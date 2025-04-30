<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Shortcode for the Crime Reporting Form
add_shortcode('sandcrime_reporting_form', 'sandcrime_render_reporting_form');
if (!function_exists('sandcrime_render_reporting_form')) {
    function sandcrime_render_reporting_form() {
        ob_start();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sandcrime_report_submit'])) {
            sandcrime_handle_form_submission();
        }

        ?>
        <form id="sandcrime-report-form" method="post" enctype="multipart/form-data">
            <h2>Step 1: Location</h2>
            <label for="location">Enter Address or Select Zone:</label>
            <input type="text" id="location" name="location" required>
            <p><strong>OR</strong></p>
            <label for="zone">Select Zone:</label>
            <select id="zone" name="zone">
                <option value="north">North</option>
                <option value="south">South</option>
                <option value="east">East</option>
                <option value="west">West</option>
            </select>

            <h2>Step 2: Crime Details</h2>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required>
            <label for="category">Crime Category:</label>
            <select id="category" name="category" required>
                <option value="theft">Theft</option>
                <option value="vandalism">Vandalism</option>
                <option value="assault">Assault</option>
                <option value="burglary">Burglary</option>
            </select>
            <label for="datetime">Date and Time:</label>
            <input type="datetime-local" id="datetime" name="datetime" value="<?php echo date('Y-m-d\TH:i'); ?>" required>

            <h2>Step 3: Involved Security Groups</h2>
            <label for="security_groups">Select Security Groups:</label>
            <select id="security_groups" name="security_groups[]" multiple>
                <?php sandcrime_render_security_groups_options(); ?>
            </select>

            <h2>Step 4: Description and Photos</h2>
            <label for="description">Description:</label>
            <textarea id="description" name="description" required></textarea>
            <label for="photos">Upload Photos:</label>
            <input type="file" id="photos" name="photos[]" multiple>

            <input type="submit" name="sandcrime_report_submit" value="Submit Report">
        </form>
        <?php

        return ob_get_clean();
    }
}

if (!function_exists('sandcrime_handle_form_submission')) {
    function sandcrime_handle_form_submission() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sandcrime_reports';

        $location = sanitize_text_field($_POST['location']);
        $zone = sanitize_text_field($_POST['zone']);
        $title = sanitize_text_field($_POST['title']);
        $category = sanitize_text_field($_POST['category']);
        $datetime = sanitize_text_field($_POST['datetime']);
        $security_groups = isset($_POST['security_groups']) ? implode(',', array_map('intval', $_POST['security_groups'])) : '';
        $description = sanitize_textarea_field($_POST['description']);

        $photo_urls = [];
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['name'] as $key => $name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $uploaded = wp_handle_upload([
                        'name' => $_FILES['photos']['name'][$key],
                        'type' => $_FILES['photos']['type'][$key],
                        'tmp_name' => $_FILES['photos']['tmp_name'][$key],
                        'error' => $_FILES['photos']['error'][$key],
                        'size' => $_FILES['photos']['size'][$key]
                    ], ['test_form' => false]);

                    if (isset($uploaded['url'])) {
                        $photo_urls[] = $uploaded['url'];
                    }
                }
            }
        }

        $wpdb->insert($table_name, [
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'date_time' => $datetime,
            'location' => !empty($location) ? $location : $zone,
            'result_status' => 'Pending Review',
            'security_groups' => $security_groups,
            'photo_attachments' => !empty($photo_urls) ? implode(',', $photo_urls) : ''
        ]);

        echo '<div class="updated"><p>Crime report submitted successfully!</p></div>';
    }
}