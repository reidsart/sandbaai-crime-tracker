<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_security_groups_page() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'sandcrime'));
    }

    // Handle form submissions and actions first
    handle_group_member_actions();
    handle_group_actions();

    // Get all security groups
    global $wpdb;
    $groups = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}sandcrime_security_groups ORDER BY name ASC"
    );

    // Enqueue required scripts and styles
    wp_enqueue_script('sandcrime-security-groups', 
        SANDCRIME_PLUGIN_URL . 'assets/js/security-groups.js',
        array('jquery'),
        SANDCRIME_VERSION,
        true
    );

    wp_localize_script('sandcrime-security-groups', 'sandcrimeGroups', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sandcrime_security_groups'),
        'i18n' => array(
            'confirmDelete' => __('Are you sure you want to delete this group?', 'sandcrime'),
            'confirmRemoveMember' => __('Are you sure you want to remove this member?', 'sandcrime'),
            'searchingUsers' => __('Searching users...', 'sandcrime'),
            'noUsersFound' => __('No users found.', 'sandcrime'),
            'errorLoading' => __('Error loading data.', 'sandcrime')
        )
    ));

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Security Groups', 'sandcrime'); ?></h1>
        <a href="#" class="page-title-action add-group"><?php esc_html_e('Add New Group', 'sandcrime'); ?></a>
        <hr class="wp-header-end">

        <?php
        // Show any admin notices
        settings_errors('sandcrime_messages');
        ?>

        <div class="sandcrime-security-groups-wrapper">
            <!-- Groups List -->
            <div class="groups-list">
                <?php if (empty($groups)): ?>
                    <p><?php esc_html_e('No security groups found.', 'sandcrime'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Name', 'sandcrime'); ?></th>
                                <th scope="col"><?php esc_html_e('Description', 'sandcrime'); ?></th>
                                <th scope="col"><?php esc_html_e('Members', 'sandcrime'); ?></th>
                                <th scope="col"><?php esc_html_e('Actions', 'sandcrime'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                                <?php
                                $member_count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}sandcrime_group_members WHERE group_id = %d",
                                    $group->id
                                ));
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="#" class="edit-group" data-group-id="<?php echo esc_attr($group->id); ?>">
                                                <?php echo esc_html($group->name); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html($group->description); ?></td>
                                    <td>
                                        <a href="#" class="manage-members" data-group-id="<?php echo esc_attr($group->id); ?>">
                                            <?php echo esc_html($member_count); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="#" class="edit-group" data-group-id="<?php echo esc_attr($group->id); ?>">
                                                    <?php esc_html_e('Edit', 'sandcrime'); ?>
                                                </a> |
                                            </span>
                                            <span class="members">
                                                <a href="#" class="manage-members" data-group-id="<?php echo esc_attr($group->id); ?>">
                                                    <?php esc_html_e('Members', 'sandcrime'); ?>
                                                </a> |
                                            </span>
                                            <span class="delete">
                                                <a href="#" class="delete-group" data-group-id="<?php echo esc_attr($group->id); ?>">
                                                    <?php esc_html_e('Delete', 'sandcrime'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Group Form Modal -->
        <div id="group-form-modal" class="sandcrime-modal" style="display:none;">
            <div class="sandcrime-modal-content">
                <span class="sandcrime-modal-close">&times;</span>
                <h2 id="modal-title"><?php esc_html_e('Add Security Group', 'sandcrime'); ?></h2>
                <form id="group-form" method="post">
                    <?php wp_nonce_field('sandcrime_security_group', 'security_group_nonce'); ?>
                    <input type="hidden" name="group_id" id="group_id" value="">
                    
                    <div class="form-field">
                        <label for="group_name"><?php esc_html_e('Group Name', 'sandcrime'); ?></label>
                        <input type="text" name="group_name" id="group_name" required>
                    </div>
                    
                    <div class="form-field">
                        <label for="group_description"><?php esc_html_e('Description', 'sandcrime'); ?></label>
                        <textarea name="group_description" id="group_description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-field">
                        <label>
                            <input type="checkbox" name="group_enabled" id="group_enabled" value="1">
                            <?php esc_html_e('Enabled', 'sandcrime'); ?>
                        </label>
                    </div>

                    <div class="submit-wrapper">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Group', 'sandcrime'); ?>
                        </button>
                        <button type="button" class="button sandcrime-modal-cancel">
                            <?php esc_html_e('Cancel', 'sandcrime'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Members Modal -->
        <div id="members-modal" class="sandcrime-modal" style="display:none;">
            <div class="sandcrime-modal-content">
                <span class="sandcrime-modal-close">&times;</span>
                <h2 id="members-modal-title"><?php esc_html_e('Manage Group Members', 'sandcrime'); ?></h2>
                
                <div class="members-search">
                    <input type="text" id="member-search" placeholder="<?php esc_attr_e('Search users...', 'sandcrime'); ?>">
                </div>

                <form id="members-form" method="post">
                    <?php wp_nonce_field('sandcrime_group_members', 'group_members_nonce'); ?>
                    <input type="hidden" name="group_id" id="member_group_id" value="">
                    
                    <div class="current-members">
                        <h3><?php esc_html_e('Current Members', 'sandcrime'); ?></h3>
                        <div id="current-members-list"></div>
                    </div>

                    <div class="available-users">
                        <h3><?php esc_html_e('Available Users', 'sandcrime'); ?></h3>
                        <div id="available-users-list"></div>
                    </div>

                    <div class="submit-wrapper">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Changes', 'sandcrime'); ?>
                        </button>
                        <button type="button" class="button sandcrime-modal-cancel">
                            <?php esc_html_e('Cancel', 'sandcrime'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}