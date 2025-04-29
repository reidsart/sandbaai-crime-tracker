<?php
// Security Group Management Functions

// Create Security Group Admin Page
add_action('admin_menu', 'sandcrime_security_groups_admin_menu');
function sandcrime_security_groups_admin_menu() {
    add_submenu_page(
        'sandcrime_dashboard',
        'Manage Security Groups',
        'Security Groups',
        'manage_options',
        'sandcrime_security_groups',
        'sandcrime_security_groups_admin_page'
    );
}

function sandcrime_security_groups_admin_page() {
    ?>
    <div class="wrap">
        <h1>Manage Security Groups</h1>
        <form method="post" enctype="multipart/form-data">
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                sandcrime_save_security_group();
            }
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="title">Title</label></th>
                    <td><input type="text" id="title" name="title" required></td>
                </tr>
                <tr>
                    <th><label for="logo">Logo</label></th>
                    <td><input type="file" id="logo" name="logo"></td>
                </tr>
                <tr>
                    <th><label for="contact_numbers">Contact Numbers</label></th>
                    <td><input type="text" id="contact_numbers" name="contact_numbers" required></td>
                </tr>
                <tr>
                    <th><label for="email">Email</label></th>
                    <td><input type="email" id="email" name="email"></td>
                </tr>
                <tr>
                    <th><label for="address">Address</label></th>
                    <td><textarea id="address" name="address"></textarea></td>
                </tr>
                <tr>
                    <th><label for="website">Website</label></th>
                    <td><input type="url" id="website" name="website"></td>
                </tr>
                <tr>
                    <th><label for="description">Description</label></th>
                    <td><textarea id="description" name="description"></textarea></td>
                </tr>
            </table>
            <?php submit_button('Save Security Group'); ?>
        </form>
    </div>
    <?php
}

// Save Security Group Data
function sandcrime_save_security_group() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_groups';

    $title = sanitize_text_field($_POST['title']);
    $contact_numbers = sanitize_text_field($_POST['contact_numbers']);
    $email = sanitize_email($_POST['email']);
    $address = sanitize_textarea_field($_POST['address']);
    $website = esc_url_raw($_POST['website']);
    $description = sanitize_textarea_field($_POST['description']);

    $logo = '';
    if (!empty($_FILES['logo']['name'])) {
        $uploaded = wp_handle_upload($_FILES['logo'], ['test_form' => false]);
        if (isset($uploaded['url'])) {
            $logo = $uploaded['url'];
        }
    }

    $wpdb->insert($table_name, [
        'title' => $title,
        'logo' => $logo,
        'contact_numbers' => $contact_numbers,
        'email' => $email,
        'address' => $address,
        'website' => $website,
        'description' => $description
    ]);
    echo '<div class="updated"><p>Security Group saved successfully!</p></div>';
}
?>