<?php
if (!defined('ABSPATH')) {
    exit;
}

function display_group_members($group_id) {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sandcrime_group_members';
    
    // Get existing members
    $members = $wpdb->get_results($wpdb->prepare("
        SELECT m.*, u.display_name, u.user_login, ua.display_name as added_name
        FROM {$members_table} m
        JOIN {$wpdb->users} u ON m.user_id = u.ID
        LEFT JOIN {$wpdb->users} ua ON m.added_by = ua.ID
        WHERE m.group_id = %d
        ORDER BY u.display_name ASC
    ", $group_id));
    ?>
    
    <div class="group-members-section">
        <form method="post" action="" class="add-members-form">
            <?php wp_nonce_field('add_group_members', 'add_members_nonce'); ?>
            <input type="hidden" name="group_id" value="<?php echo esc_attr($group_id); ?>">
            
            <h3>Add Members</h3>
            <div class="member-selection">
                <select name="new_members[]" id="new_members" class="regular-text" multiple="multiple">
                    <?php
                    $users = get_users(['orderby' => 'display_name']);
                    foreach ($users as $user) {
                        // Skip users who are already members
                        if (!in_array($user->ID, array_column($members, 'user_id'))) {
                            echo '<option value="' . esc_attr($user->ID) . '">' . 
                                 esc_html($user->display_name . ' (' . $user->user_login . ')') . 
                                 '</option>';
                        }
                    }
                    ?>
                </select>
                
                <div class="member-role-select">
                    <label><input type="radio" name="member_role" value="member" checked> Member</label>
                    <label><input type="radio" name="member_role" value="admin"> Admin</label>
                </div>
                
                <?php submit_button('Add Selected Members', 'primary', 'add_members', false); ?>
            </div>
        </form>

        <div class="current-members">
            <h3>Current Members</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="members-select-all"></th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Added By</th>
                        <th>Added On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members): ?>
                        <form method="post" id="bulk-members-form">
                            <?php wp_nonce_field('bulk_member_actions', 'bulk_members_nonce'); ?>
                            <input type="hidden" name="group_id" value="<?php echo esc_attr($group_id); ?>">
                            
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><input type="checkbox" name="member_ids[]" value="<?php echo esc_attr($member->user_id); ?>"></td>
                                    <td><?php echo esc_html($member->display_name . ' (' . $member->user_login . ')'); ?></td>
                                    <td>
                                        <select name="member_roles[<?php echo esc_attr($member->user_id); ?>]" class="member-role">
                                            <option value="member" <?php selected($member->role, 'member'); ?>>Member</option>
                                            <option value="admin" <?php selected($member->role, 'admin'); ?>>Admin</option>
                                        </select>
                                    </td>
                                    <td><?php echo esc_html($member->added_name); ?></td>
                                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($member->added_at))); ?></td>
                                    <td>
                                        <button type="button" class="button button-link-delete remove-member" 
                                                data-user-id="<?php echo esc_attr($member->user_id); ?>">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <tr class="bulk-actions">
                                <td colspan="6">
                                    <select name="bulk_action">
                                        <option value="">Bulk Actions</option>
                                        <option value="remove">Remove Selected</option>
                                        <option value="make_member">Change Role to Member</option>
                                        <option value="make_admin">Change Role to Admin</option>
                                    </select>
                                    <?php submit_button('Apply', 'secondary', 'bulk_member_action', false); ?>
                                </td>
                            </tr>
                        </form>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No members found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .member-selection {
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .member-role-select {
            display: flex;
            gap: 15px;
        }
        .bulk-actions {
            background-color: #f5f5f5;
        }
        .member-role {
            min-width: 100px;
        }
    </style>
    <?php
}