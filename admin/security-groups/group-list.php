<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_display_security_groups_list() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sandcrime_groups';
    
    // Get groups with member count
    $groups = $wpdb->get_results("
        SELECT g.*, 
               COUNT(DISTINCT m.id) as member_count,
               GROUP_CONCAT(DISTINCT p.phone_number ORDER BY p.sort_order SEPARATOR '|') as phone_numbers,
               GROUP_CONCAT(DISTINCT p.label ORDER BY p.sort_order SEPARATOR '|') as phone_labels
        FROM $table_name g
        LEFT JOIN {$wpdb->prefix}sandcrime_group_members m ON g.id = m.group_id
        LEFT JOIN {$wpdb->prefix}sandcrime_group_phones p ON g.id = p.group_id
        GROUP BY g.id
        ORDER BY g.title ASC
    ");
    
    if ($groups): ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <button type="button" class="button button-primary" onclick="window.location.href='#add-new-group'">
                    Add New Security Group
                </button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped security-groups-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Logo</th>
                    <th>Name</th>
                    <th>Contact Numbers</th>
                    <th>Email</th>
                    <th>Members</th>
                    <th>Website</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td>
                            <?php if (!empty($group->logo_url)): ?>
                                <img src="<?php echo esc_url($group->logo_url); ?>" 
                                     style="max-width: 50px; height: auto;" alt="Group logo">
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($group->title); ?></strong>
                            <?php if (!empty($group->description)): ?>
                                <br>
                                <small class="description">
                                    <?php echo wp_trim_words($group->description, 10); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if (!empty($group->phone_numbers)) {
                                $numbers = explode('|', $group->phone_numbers);
                                $labels = explode('|', $group->phone_labels);
                                echo '<ul class="phone-numbers-list">';
                                for ($i = 0; $i < count($numbers); $i++) {
                                    echo '<li><strong>' . esc_html($labels[$i]) . ':</strong> ' . 
                                         esc_html($numbers[$i]) . '</li>';
                                }
                                echo '</ul>';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($group->email); ?></td>
                        <td>
                            <span class="member-count">
                                <?php echo intval($group->member_count); ?> members
                            </span>
                        </td>
                        <td>
                            <?php if ($group->website): ?>
                                <a href="<?php echo esc_url($group->website); ?>" 
                                   target="_blank" class="button button-small">
                                    Visit Website
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($group->address); ?></td>
                        <td class="action-buttons">
                            <a href="?page=sandcrime-security-groups&action=edit&id=<?php echo esc_attr($group->id); ?>" 
                               class="button button-secondary button-small">Edit</a>
                            <a href="?page=sandcrime-security-groups&action=delete&id=<?php echo esc_attr($group->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_security_group_' . $group->id); ?>" 
                               class="button button-link-delete button-small" 
                               onclick="return confirm('Are you sure you want to delete this security group? This action cannot be undone.');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="notice notice-info">
            <p>No security groups found. Create your first security group below.</p>
        </div>
    <?php endif; ?>

    <style>
    .security-groups-table {
        margin-top: 10px;
    }
    .phone-numbers-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .phone-numbers-list li {
        margin-bottom: 3px;
    }
    .member-count {
        display: inline-block;
        padding: 3px 8px;
        background: #f0f0f1;
        border-radius: 3px;
        font-size: 12px;
    }
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    .action-buttons .button-small {
        margin-top: 2px;
    }
    </style>
    <?php
}