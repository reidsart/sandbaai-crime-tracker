<?php if (!defined('ABSPATH')) exit; ?>

<div class="group-details-wrapper">
    <div class="group-header">
        <?php if ($group->logo_url): ?>
            <div class="group-logo">
                <img src="<?php echo esc_url($group->logo_url); ?>" 
                     alt="<?php echo esc_attr($group->title); ?>">
            </div>
        <?php endif; ?>
        
        <div class="group-info">
            <h2><?php echo esc_html($group->title); ?></h2>
            <div class="group-meta">
                <span class="member-count">
                    <i class="dashicons dashicons-groups"></i>
                    <?php echo number_format($group->member_count); ?> members
                </span>
                <span class="report-count">
                    <i class="dashicons dashicons-clipboard"></i>
                    <?php echo number_format($group->report_count); ?> reports
                </span>
            </div>
        </div>

        <?php if (is_user_logged_in()): ?>
            <div class="group-actions">
                <?php if (SandCrime_Group_Manager::can_manage_group($group->id)): ?>
                    <button class="button manage-group" data-id="<?php echo esc_attr($group->id); ?>">
                        Manage Group
                    </button>
                <?php else: ?>
                    <?php 
                    $is_member = SandCrime_Group_Manager::is_member($group->id, get_current_user_id());
                    if ($is_member): ?>
                        <button class="button leave-group" data-id="<?php echo esc_attr($group->id); ?>">
                            Leave Group
                        </button>
                    <?php else: ?>
                        <button class="button join-group" data-id="<?php echo esc_attr($group->id); ?>">
                            Join Group
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="group-content">
        <div class="group-description">
            <?php echo wpautop(esc_html($group->description)); ?>
        </div>

        <div class="group-contact-info">
            <h3>Contact Information</h3>
            <?php if ($group->email): ?>
                <div class="contact-item">
                    <i class="dashicons dashicons-email"></i>
                    <a href="mailto:<?php echo esc_attr($group->email); ?>">
                        <?php echo esc_html($group->email); ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($group->contacts): ?>
                <div class="contact-phones">
                    <?php foreach ($group->contacts as $contact): ?>
                        <div class="contact-item">
                            <i class="dashicons dashicons-phone"></i>
                            <span class="phone-label"><?php echo esc_html($contact->label); ?>:</span>
                            <a href="tel:<?php echo esc_attr($contact->phone_number); ?>">
                                <?php echo esc_html($contact->phone_number); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($group->website): ?>
                <div class="contact-item">
                    <i class="dashicons dashicons-admin-site"></i>
                    <a href="<?php echo esc_url($group->website); ?>" target="_blank">
                        <?php echo esc_html($group->website); ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($group->address): ?>
                <div class="contact-item">
                    <i class="dashicons dashicons-location"></i>
                    <?php echo esc_html($group->address); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($group->coverage_area): ?>
            <div class="group-coverage">
                <h3>Coverage Area</h3>
                <div id="coverage-map" class="coverage-map" 
                     data-coverage='<?php echo esc_attr($group->coverage_area); ?>'></div>
            </div>
        <?php endif; ?>

        <?php if ($group->admins): ?>
            <div class="group-admins">
                <h3>Group Administrators</h3>
                <div class="admin-list">
                    <?php foreach ($group->admins as $admin): ?>
                        <div class="admin-item">
                            <?php echo get_avatar($admin->user_id, 40); ?>
                            <div class="admin-info">
                                <span class="admin-name"><?php echo esc_html($admin->display_name); ?></span>
                                <?php if (SandCrime_Group_Manager::can_manage_group($group->id)): ?>
                                    <span class="admin-email"><?php echo esc_html($admin->user_email); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (SandCrime_Group_Manager::can_manage_group($group->id)): ?>
            <div class="group-management">
                <h3>Group Management</h3>
                <div class="management-actions">
                    <button class="button edit-group" data-id="<?php echo esc_attr($group->id); ?>">
                        Edit Group Details
                    </button>
                    <button class="button manage-members" data-id="<?php echo esc_attr($group->id); ?>">
                        Manage Members
                    </button>
                    <button class="button edit-coverage" data-id="<?php echo esc_attr($group->id); ?>">
                        Edit Coverage Area
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.group-details-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}
.group-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
}
.group-logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
}
.group-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.group-info {
    flex: 1;
}
.group-meta {
    display: flex;
    gap: 20px;
    color: #666;
}
.group-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.group-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}
.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.coverage-map {
    height: 400px;
    border-radius: 4px;
    border: 1px solid #ddd;
    margin-top: 10px;
}
.admin-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}
.admin-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}
.admin-info {
    display: flex;
    flex-direction: column;
}
.admin-email {
    font-size: 12px;
    color: #666;
}
.management-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
@media (max-width: 768px) {
    .group-content {
        grid-template-columns: 1fr;
    }
}
</style>