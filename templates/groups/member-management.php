<?php if (!defined('ABSPATH')) exit; ?>

<div class="member-management-wrapper">
    <div class="management-header">
        <h2>Manage Group Members</h2>
        <button class="button add-member" id="add-member-btn">Add New Member</button>
    </div>

    <div class="member-filters">
        <input type="text" id="member-search" placeholder="Search members...">
        <select id="role-filter">
            <option value="">All Roles</option>
            <option value="admin">Administrators</option>
            <option value="moderator">Moderators</option>
            <option value="member">Regular Members</option>
        </select>
    </div>

    <div class="members-list">
        <?php foreach ($members as $member): ?>
            <div class="member-item" data-id="<?php echo esc_attr($member->user_id); ?>">
                <div class="member-info">
                    <?php echo get_avatar($member->user_id, 40); ?>
                    <div class="member-details">
                        <span class="member-name"><?php echo esc_html($member->display_name); ?></span>
                        <span class="member-email"><?php echo esc_html($member->user_email); ?></span>
                        <span class="member-joined">
                            Joined: <?php echo esc_html(date('M j, Y', strtotime($member->joined_date))); ?>
                        </span>
                    </div>
                </div>

                <div class="member-role">
                    <select class="role-select" data-user-id="<?php echo esc_attr($member->user_id); ?>">
                        <option value="member" <?php selected($member->role, 'member'); ?>>Member</option>
                        <option value="moderator" <?php selected($member->role, 'moderator'); ?>>Moderator</option>
                        <option value="admin" <?php selected($member->role, 'admin'); ?>>Administrator</option>
                    </select>
                </div>

                <div class="member-actions">
                    <button class="button remove-member" data-user-id="<?php echo esc_attr($member->user_id); ?>">
                        Remove
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Member Modal -->
    <div id="add-member-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Add New Member</h3>
            
            <form id="add-member-form">
                <div class="form-row">
                    <label for="user-search">Search User:</label>
                    <input type="text" id="user-search" placeholder="Type to search users...">
                    <div id="user-search-results"></div>
                </div>

                <div class="form-row">
                    <label for="member-role">Role:</label>
                    <select id="member-role">
                        <option value="member">Member</option>
                        <option value="moderator">Moderator</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">Add Member</button>
                    <button type="button" class="button cancel-add">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.member-management-wrapper {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}
.management-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.member-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}
.member-filters input,
.member-filters select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.member-filters input {
    flex: 1;
}
.members-list {
    border: 1px solid #ddd;
    border-radius: 4px;
}
.member-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    border-bottom: 1px solid #ddd;
}
.member-item:last-child {
    border-bottom: none;
}
.member-info {
    display: flex;
    align-items: center;
    gap: 15px;
}
.member-details {
    display: flex;
    flex-direction: column;
}
.member-name {
    font-weight: 600;
}
.member-email {
    color: #666;
    font-size: 14px;
}
.member-joined {
    font-size: 12px;
    color: #888;
}
.member-role {
    min-width: 120px;
}
.role-select {
    width: 100%;
    padding: 6px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.member-actions {
    display: flex;
    gap: 10px;
}
#user-search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
    display: none;
}
.user-search-item {
    padding: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-search-item:hover {
    background: #f8f9fa;
}
.form-row {
    margin-bottom: 15px;
    position: relative;
}
.form-row label {
    display: block;
    margin-bottom: 5px;
}
.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}
</style>