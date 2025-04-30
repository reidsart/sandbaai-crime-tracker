<?php
if (!defined('ABSPATH')) exit;

$template_types = array(
    'email' => __('Email', 'sandcrime'),
    'push' => __('Push Notification', 'sandcrime'),
    'sms' => __('SMS', 'sandcrime')
);
?>

<div class="wrap sandcrime-templates">
    <h1 class="wp-heading-inline"><?php _e('Notification Templates', 'sandcrime'); ?></h1>
    <a href="#" class="page-title-action" id="add-new-template"><?php _e('Add New Template', 'sandcrime'); ?></a>
    <hr class="wp-header-end">

    <div class="templates-list-wrapper">
        <div class="templates-filters">
            <select id="template-type-filter">
                <option value=""><?php _e('All Types', 'sandcrime'); ?></option>
                <?php foreach ($template_types as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="template-status-filter">
                <option value=""><?php _e('All Status', 'sandcrime'); ?></option>
                <option value="active"><?php _e('Active', 'sandcrime'); ?></option>
                <option value="inactive"><?php _e('Inactive', 'sandcrime'); ?></option>
            </select>

            <input type="text" 
                   id="template-search" 
                   placeholder="<?php esc_attr_e('Search templates...', 'sandcrime'); ?>">
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-name"><?php _e('Name', 'sandcrime'); ?></th>
                    <th class="column-type"><?php _e('Type', 'sandcrime'); ?></th>
                    <th class="column-description"><?php _e('Description', 'sandcrime'); ?></th>
                    <th class="column-status"><?php _e('Status', 'sandcrime'); ?></th>
                    <th class="column-updated"><?php _e('Last Updated', 'sandcrime'); ?></th>
                    <th class="column-actions"><?php _e('Actions', 'sandcrime'); ?></th>
                </tr>
            </thead>
            <tbody id="template-list">
                <?php foreach ($templates as $template): ?>
                    <tr data-template-id="<?php echo esc_attr($template->id); ?>">
                        <td class="column-name">
                            <strong>
                                <a href="#" class="edit-template">
                                    <?php echo esc_html($template->name); ?>
                                </a>
                            </strong>
                        </td>
                        <td class="column-type">
                            <?php echo esc_html($template_types[$template->type] ?? $template->type); ?>
                        </td>
                        <td class="column-description">
                            <?php echo esc_html($template->description); ?>
                        </td>
                        <td class="column-status">
                            <span class="status-badge status-<?php echo esc_attr($template->status); ?>">
                                <?php echo esc_html(ucfirst($template->status)); ?>
                            </span>
                        </td>
                        <td class="column-updated">
                            <?php
                            $updated_at = strtotime($template->updated_at);
                            echo esc_html(human_time_diff($updated_at) . ' ago');
                            ?>
                        </td>
                        <td class="column-actions">
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="#" class="edit-template">
                                        <?php _e('Edit', 'sandcrime'); ?>
                                    </a> |
                                </span>
                                <span class="duplicate">
                                    <a href="#" class="duplicate-template">
                                        <?php _e('Duplicate', 'sandcrime'); ?>
                                    </a> |
                                </span>
                                <span class="preview">
                                    <a href="#" class="preview-template">
                                        <?php _e('Preview', 'sandcrime'); ?>
                                    </a> |
                                </span>
                                <span class="delete">
                                    <a href="#" class="delete-template">
                                        <?php _e('Delete', 'sandcrime'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Template Editor Modal -->
<div id="template-editor" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Edit Template', 'sandcrime'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="template-form">
                <input type="hidden" name="template_id" id="template_id" value="">
                <?php wp_nonce_field('sandcrime_templates', 'template_nonce'); ?>

                <div class="form-field">
                    <label for="template_name"><?php _e('Template Name', 'sandcrime'); ?></label>
                    <input type="text" 
                           id="template_name" 
                           name="name" 
                           required>
                </div>

                <div class="form-field">
                    <label for="template_type"><?php _e('Type', 'sandcrime'); ?></label>
                    <select id="template_type" name="type" required>
                        <?php foreach ($template_types as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="template_description"><?php _e('Description', 'sandcrime'); ?></label>
                    <textarea id="template_description" name="description"></textarea>
                </div>

                <div class="form-field">
                    <label for="template_content"><?php _e('Content', 'sandcrime'); ?></label>
                    <div class="template-editor-wrapper">
                        <div class="editor-toolbar">
                            <button type="button" class="insert-variable">
                                <?php _e('Insert Variable', 'sandcrime'); ?>
                            </button>
                            <button type="button" class="preview-template">
                                <?php _e('Preview', 'sandcrime'); ?>
                            </button>
                        </div>
                        <?php wp_editor('', 'template_content', array(
                            'media_buttons' => false,
                            'textarea_rows' => 15,
                            'teeny' => true
                        )); ?>
                    </div>
                </div>

                <div class="form-field">
                    <label>
                        <input type="checkbox" 
                               name="status" 
                               value="active" 
                               checked>
                        <?php _e('Active', 'sandcrime'); ?>
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="button" id="cancel-template">
                <?php _e('Cancel', 'sandcrime'); ?>
            </button>
            <button type="button" class="button button-primary" id="save-template">
                <?php _e('Save Template', 'sandcrime'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="preview-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Template Preview', 'sandcrime'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="preview-container"></div>
        </div>
    </div>
</div>

<!-- Variables Modal -->
<div id="variables-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Insert Variable', 'sandcrime'); ?></h2>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="variables-list">
                <?php
                $variables = array(
                    'recipient' => array(
                        'name' => '{{recipient_name}}',
                        'email' => '{{recipient_email}}',
                    ),
                    'notification' => array(
                        'title' => '{{notification_title}}',
                        'message' => '{{notification_message}}',
                        'date' => '{{notification_date}}',
                    ),
                    'site' => array(
                        'name' => '{{site_name}}',
                        'url' => '{{site_url}}',
                    )
                );

                foreach ($variables as $group => $vars): ?>
                    <div class="variable-group">
                        <h4><?php echo esc_html(ucfirst($group)); ?></h4>
                        <ul>
                            <?php foreach ($vars as $label => $variable): ?>
                                <li>
                                    <button type="button" 
                                            class="insert-var-btn" 
                                            data-variable="<?php echo esc_attr($variable); ?>">
                                        <?php echo esc_html($label); ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>