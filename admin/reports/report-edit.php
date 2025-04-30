<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_report_edit_page($report_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_reports';

    // Handle report updates
    if (isset($_POST['report_action']) && isset($_POST['report_id']) && 
        wp_verify_nonce($_POST['sandcrime_report_nonce'], 'sandcrime_report_action')) {
        
        $report_id = intval($_POST['report_id']);
        $current_user = wp_get_current_user();
        
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'category' => sanitize_text_field($_POST['category']),
            'location' => sanitize_text_field($_POST['location']),
            'result_status' => sanitize_text_field($_POST['report_action']),
            'last_edited_by' => $current_user->user_login,
            'last_edited_at' => current_time('mysql')
        );
        
        $wpdb->update(
            $table_name,
            $data,
            ['id' => $report_id]
        );
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Report updated successfully.</p></div>';
        });
    }

    $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $report_id));
    
    if (!$report) {
        echo '<div class="wrap"><p>Report not found.</p></div>';
        return;
    }
    ?>
    <div class="wrap">
        <h1>
            Edit Report
            <a href="?page=sandcrime-reports" class="page-title-action">Back to Reports</a>
        </h1>
        
        <div class="card">
            <form method="post" class="report-edit-form">
                <?php wp_nonce_field('sandcrime_report_action', 'sandcrime_report_nonce'); ?>
                <input type="hidden" name="report_id" value="<?php echo esc_attr($report->id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label for="title">Title</label></th>
                        <td>
                            <input type="text" id="title" name="title" class="regular-text" 
                                   value="<?php echo esc_attr($report->title); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="category">Category</label></th>
                        <td>
                            <select id="category" name="category" required>
                                <?php
                                $categories = $wpdb->get_results("SELECT name FROM {$wpdb->prefix}sandcrime_categories");
                                foreach ($categories as $cat) {
                                    echo '<option value="' . esc_attr($cat->name) . '" ' . 
                                         selected($report->category, $cat->name, false) . '>' . 
                                         esc_html($cat->name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="location">Location</label></th>
                        <td>
                            <input type="text" id="location" name="location" class="regular-text" 
                                   value="<?php echo esc_attr($report->location); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td>
                            <textarea id="description" name="description" class="large-text" rows="5" required><?php 
                                echo esc_textarea($report->description); 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Reported By</th>
                        <td>
                            <?php echo esc_html($report->user_login ? $report->user_login : 'Anonymous'); ?>
                            <?php if ($report->last_edited_by): ?>
                                <br>
                                <span class="description">
                                    Last edited by <?php echo esc_html($report->last_edited_by); ?> 
                                    on <?php echo esc_html(date('Y-m-d H:i:s', strtotime($report->last_edited_at))); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Date/Time</th>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($report->date_time))); ?></td>
                    </tr>
                    <tr>
                        <th><label for="report_action">Status</label></th>
                        <td>
                            <select name="report_action" id="report_action">
                                <?php
                                $statuses = array(
                                    'Pending Review' => 'Pending Review',
                                    'In Progress' => 'In Progress',
                                    'Resolved' => 'Resolved',
                                    'Closed' => 'Closed'
                                );
                                foreach ($statuses as $value => $label) {
                                    echo '<option value="' . esc_attr($value) . '" ' . 
                                         selected($report->result_status, $value, false) . '>' . 
                                         esc_html($label) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <?php if (!empty($report->photo_attachments)): ?>
                        <tr>
                            <th>Photos</th>
                            <td class="report-photos">
                                <?php
                                $photos = explode(',', $report->photo_attachments);
                                foreach ($photos as $photo_url): ?>
                                    <div class="photo-item">
                                        <a href="<?php echo esc_url($photo_url); ?>" target="_blank">
                                            <img src="<?php echo esc_url($photo_url); ?>" 
                                                 alt="Report photo">
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Security Groups Notified</th>
                        <td>
                            <?php
                            $security_groups = !empty($report->security_groups) ? 
                                explode(',', $report->security_groups) : array();
                            
                            if (!empty($security_groups)) {
                                echo '<ul class="security-groups-list">';
                                foreach ($security_groups as $group_id) {
                                    $group = $wpdb->get_row($wpdb->prepare(
                                        "SELECT title FROM {$wpdb->prefix}sandcrime_groups WHERE id = %d",
                                        $group_id
                                    ));
                                    if ($group) {
                                        echo '<li>' . esc_html($group->title) . '</li>';
                                    }
                                }
                                echo '</ul>';
                            } else {
                                echo '<p>No security groups were notified.</p>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Update Report'); ?>
            </form>
        </div>
    </div>

    <style>
    .report-photos {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }
    .photo-item {
        position: relative;
    }
    .photo-item img {
        max-width: 100%;
        height: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .security-groups-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .security-groups-list li {
        display: inline-block;
        margin-right: 10px;
        padding: 5px 10px;
        background: #f0f0f1;
        border-radius: 3px;
    }
    </style>
    <?php
}