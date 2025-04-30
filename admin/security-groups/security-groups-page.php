<?php
if (!defined('ABSPATH')) {
    exit;
}

function sandcrime_security_groups_page() {
    // Handle form submissions and actions
    handle_group_member_actions();
    handle_security_group_submission();
    handle_security_group_deletion();

    $editing_group = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $editing_group = get_security_group(intval($_GET['id']));
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php if ($editing_group): ?>
            <!-- Edit Security Group Form -->
            <h2 class="nav-tab-wrapper">
                <a href="#group-details" class="nav-tab nav-tab-active">Group Details</a>
                <a href="#group-members" class="nav-tab">Group Members</a>
            </h2>
            
            <div class="tab-content">
                <div id="group-details" class="tab-pane active">
                    <div class="card">
                        <h2>Edit Security Group</h2>
                        <?php display_security_group_form($editing_group); ?>
                    </div>
                </div>
                
                <div id="group-members" class="tab-pane" style="display: none;">
                    <div class="card">
                        <h3>Group Members</h3>
                        <?php display_group_members($editing_group->id); ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php sandcrime_display_security_groups_list(); ?>
            
            <!-- Add New Security Group Form -->
            <div class="card" style="margin-top: 20px;">
                <h2>Add New Security Group</h2>
                <?php display_security_group_form(); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}