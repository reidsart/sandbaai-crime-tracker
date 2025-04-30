<?php
if (!defined('ABSPATH')) {
    exit;
}

function handle_security_group_submission() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
        !isset($_POST['sandcrime_security_group_nonce']) || 
        !wp_verify_nonce($_POST['sandcrime_security_group_nonce'], 'sandcrime_security_group_action')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_groups';
    $phones_table = $wpdb->prefix . 'sandcrime_group_phones';

    // Handle logo upload
    $logo_id = null;
    $logo_url = null;
    if (!empty($_FILES['logo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $logo_id = media_handle_upload('logo', 0);
        if (!is_wp_error($logo_id)) {
            $logo_url = wp_get_attachment_url($logo_id);
        }
    }

    // Clean website URL
    $website = sanitize_text_field($_POST['website']);
    if (!empty($website) && !preg_match("~^(?:f|ht)tps?://~i", $website)) {
        $website = "http://" . $website;
    }

    $data = array(
        'title' => sanitize_text_field($_POST['title']),
        'email' => sanitize_email($_POST['email']),
        'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
        'website' => $website ? esc_url_raw($website) : '',
        'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : ''
    );

    // Add logo data if uploaded
    if ($logo_id && $logo_url) {
        $data['logo_id'] = $logo_id;
        $data['logo_url'] = $logo_url;
    }

    // Handle update or insert
    if (isset($_POST['group_id']) && !empty($_POST['group_id'])) {
        $group_id = intval($_POST['group_id']);
        
        // Handle logo replacement
        if ($logo_id) {
            $old_logo_id = $wpdb->get_var($wpdb->prepare(
                "SELECT logo_id FROM $table_name WHERE id = %d",
                $group_id
            ));
            if ($old_logo_id) {
                wp_delete_attachment($old_logo_id, true);
            }
        }

        $result = $wpdb->update($table_name, $data, ['id' => $group_id]);
        
        // Delete existing phone numbers
        $wpdb->delete($phones_table, ['group_id' => $group_id]);
    } else {
        $result = $wpdb->insert($table_name, $data);
        $group_id = $wpdb->insert_id;
    }

    // Handle phone numbers
    if ($result !== false && isset($_POST['phone_numbers']) && isset($_POST['phone_labels'])) {
        $phone_numbers = $_POST['phone_numbers'];
        $phone_labels = $_POST['phone_labels'];
        
        for ($i = 0; $i < count($phone_numbers); $i++) {
            if (!empty($phone_numbers[$i])) {
                $wpdb->insert(
                    $phones_table,
                    array(
                        'group_id' => $group_id,
                        'phone_number' => sanitize_text_field($phone_numbers[$i]),
                        'label' => sanitize_text_field($phone_labels[$i]),
                        'sort_order' => $i
                    ),
                    array('%d', '%s', '%s', '%d')
                );
            }
        }
    }

    if ($result === false) {
        add_action('admin_notices', function() use ($wpdb) {
            echo '<div class="notice notice-error is-dismissible"><p>Database error: ' . 
                 esc_html($wpdb->last_error) . '</p></div>';
        });
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Security group saved successfully!</p></div>';
        });
        
        if (!isset($_POST['group_id'])) {
            wp_redirect(add_query_arg(
                array(
                    'page' => 'sandcrime-security-groups',
                    'action' => 'edit',
                    'id' => $group_id
                ),
                admin_url('admin.php')
            ));
            exit;
        }
    }
}

function handle_security_group_deletion() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'delete' || !isset($_GET['id'])) {
        return;
    }

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_security_group_' . $_GET['id'])) {
        wp_die('Invalid nonce');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_groups';
    $group_id = intval($_GET['id']);
    
    // Get logo ID before deleting the group
    $logo_id = $wpdb->get_var($wpdb->prepare(
        "SELECT logo_id FROM $table_name WHERE id = %d",
        $group_id
    ));

    // Delete related data first
    $wpdb->delete($wpdb->prefix . 'sandcrime_group_phones', ['group_id' => $group_id]);
    $wpdb->delete($wpdb->prefix . 'sandcrime_group_members', ['group_id' => $group_id]);

    // Delete the group
    $result = $wpdb->delete($table_name, ['id' => $group_id]);
    
    if ($result !== false) {
        // Delete the logo if it exists
        if ($logo_id) {
            wp_delete_attachment($logo_id, true);
        }
        
        wp_redirect(add_query_arg(
            array(
                'page' => 'sandcrime-security-groups',
                'deleted' => '1'
            ),
            admin_url('admin.php')
        ));
        exit;
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>Error deleting security group.</p></div>';
        });
    }
}

function get_security_group($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sandcrime_groups WHERE id = %d",
        $id
    ));
}