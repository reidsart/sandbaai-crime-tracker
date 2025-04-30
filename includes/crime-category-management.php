<?php
// Crime Category Management Functions

// Create Crime Categories Admin Page
add_action('admin_menu', 'sandcrime_crime_categories_menu');
function sandcrime_crime_categories_menu() {
    add_submenu_page(
        'sandcrime_dashboard',
        'Manage Crime Categories',
        'Crime Categories',
        'manage_options',
        'sandcrime_crime_categories',
        'sandcrime_crime_categories_page'
    );
}

// Crime Categories Page
function sandcrime_crime_categories_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_categories';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sandcrime_save_category'])) {
        $category_name = sanitize_text_field($_POST['category_name']);
        $wpdb->insert($table_name, ['name' => $category_name]);
        echo '<div class="updated"><p>Category added successfully!</p></div>';
    }

    if (isset($_GET['delete']) && $_GET['delete']) {
        $id = intval($_GET['delete']);
        $wpdb->delete($table_name, ['id' => $id]);
        echo '<div class="updated"><p>Category deleted successfully!</p></div>';
    }

    $categories = $wpdb->get_results("SELECT * FROM $table_name");
    ?>
    <div class="wrap">
        <h1>Manage Crime Categories</h1>
        <form method="post">
            <label for="category_name">Add New Category:</label>
            <input type="text" id="category_name" name="category_name" required>
            <button type="submit" name="sandcrime_save_category">Add Category</button>
        </form>
        <h2>Existing Categories</h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo $category->id; ?></td>
                        <td><?php echo esc_html($category->name); ?></td>
                        <td>
                            <a href="?page=sandcrime_crime_categories&delete=<?php echo $category->id; ?>" onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Create the database table for crime categories during plugin activation
register_activation_hook(__FILE__, 'sandcrime_create_category_table');
function sandcrime_create_category_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_categories';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
?>