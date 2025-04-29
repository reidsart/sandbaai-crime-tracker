<?php
// WhatsApp Notification Integration

function sandcrime_send_whatsapp_notification($crime_report_id) {
    // Fetch crime report details
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_reports';
    $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $crime_report_id));

    if ($report) {
        $message = "New Crime Report:\n";
        $message .= "Title: " . $report->title . "\n";
        $message .= "Description: " . $report->description . "\n";
        $message .= "Location: " . $report->location . "\n";
        $message .= "Date/Time: " . $report->date_time . "\n";

        // WhatsApp API endpoint (replace with your provider's API)
        $api_url = 'https://api.whatsapp.com/send';
        $phone_number = 'YOUR_PHONE_NUMBER';
        $api_key = 'YOUR_API_KEY';

/* disable until ready to implement
$response = wp_remote_post($api_url, [
    'body' => [
        'phone' => $phone_number,
        'message' => $message,
        'api_key' => $api_key
    ]
]);
*/

        if (is_wp_error($response)) {
            error_log('WhatsApp Notification Error: ' . $response->get_error_message());
        }
    }
}

// Hook into report submission
add_action('sandcrime_report_submitted', 'sandcrime_send_whatsapp_notification', 10, 1);
?>