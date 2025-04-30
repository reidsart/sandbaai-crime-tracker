<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Templates {
    private $post_type = 'notification_template';
    private $settings;

    public function __construct() {
        $this->settings = new SandCrime_Notification_Settings();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_template'), 10, 2);
        add_filter('manage_notification_template_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_notification_template_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-notification_template_sortable_columns', array($this, 'set_sortable_columns'));
    }

    public function register_post_type() {
        register_post_type('notification_template', array(
            'labels' => array(
                'name' => __('Notification Templates', 'sandcrime'),
                'singular_name' => __('Notification Template', 'sandcrime'),
                'add_new' => __('Add New Template', 'sandcrime'),
                'add_new_item' => __('Add New Notification Template', 'sandcrime'),
                'edit_item' => __('Edit Notification Template', 'sandcrime'),
                'new_item' => __('New Notification Template', 'sandcrime'),
                'view_item' => __('View Notification Template', 'sandcrime'),
                'search_items' => __('Search Templates', 'sandcrime'),
                'not_found' => __('No templates found', 'sandcrime'),
                'not_found_in_trash' => __('No templates found in trash', 'sandcrime'),
                'menu_name' => __('Templates', 'sandcrime')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'sandcrime-notifications',
            'supports' => array('title', 'revisions'),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_post' => 'manage_options',
                'delete_post' => 'manage_options'
            ),
            'map_meta_cap' => true
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'template_details',
            __('Template Details', 'sandcrime'),
            array($this, 'render_details_meta_box'),
            'notification_template',
            'normal',
            'high'
        );

        add_meta_box(
            'template_content',
            __('Template Content', 'sandcrime'),
            array($this, 'render_content_meta_box'),
            'notification_template',
            'normal',
            'high'
        );

        add_meta_box(
            'template_preview',
            __('Template Preview', 'sandcrime'),
            array($this, 'render_preview_meta_box'),
            'notification_template',
            'side',
            'default'
        );

        add_meta_box(
            'template_placeholders',
            __('Available Placeholders', 'sandcrime'),
            array($this, 'render_placeholders_meta_box'),
            'notification_template',
            'side',
            'default'
        );
    }

    public function render_details_meta_box($post) {
        $template_data = get_post_meta($post->ID, '_template_data', true);
        wp_nonce_field('sandcrime_template_nonce', 'template_nonce');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="template_type">
                        <?php esc_html_e('Template Type', 'sandcrime'); ?>
                    </label>
                </th>
                <td>
                    <select name="template_type" id="template_type" required>
                        <option value=""><?php esc_html_e('Select Type', 'sandcrime'); ?></option>
                        <option value="email" <?php selected(isset($template_data['type']) && $template_data['type'] === 'email'); ?>>
                            <?php esc_html_e('Email', 'sandcrime'); ?>
                        </option>
                        <option value="push" <?php selected(isset($template_data['type']) && $template_data['type'] === 'push'); ?>>
                            <?php esc_html_e('Push Notification', 'sandcrime'); ?>
                        </option>
                        <option value="sms" <?php selected(isset($template_data['type']) && $template_data['type'] === 'sms'); ?>>
                            <?php esc_html_e('SMS', 'sandcrime'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="template_category">
                        <?php esc_html_e('Category', 'sandcrime'); ?>
                    </label>
                </th>
                <td>
                    <select name="template_category" id="template_category">
                        <option value=""><?php esc_html_e('Select Category', 'sandcrime'); ?></option>
                        <?php
                        $categories = array(
                            'alert' => __('Alert', 'sandcrime'),
                            'notification' => __('Notification', 'sandcrime'),
                            'reminder' => __('Reminder', 'sandcrime'),
                            'update' => __('Update', 'sandcrime'),
                            'report' => __('Report', 'sandcrime')
                        );

                        foreach ($categories as $value => $label) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($value),
                                selected(isset($template_data['category']) && $template_data['category'] === $value, true, false),
                                esc_html($label)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="template_description">
                        <?php esc_html_e('Description', 'sandcrime'); ?>
                    </label>
                </th>
                <td>
                    <textarea name="template_description" 
                              id="template_description" 
                              class="large-text" 
                              rows="3"><?php echo esc_textarea(isset($template_data['description']) ? $template_data['description'] : ''); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Brief description of when and how this template should be used.', 'sandcrime'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_content_meta_box($post) {
        $template_data = get_post_meta($post->ID, '_template_data', true);
        ?>
        <div class="template-content-wrapper">
            <div class="template-subject" style="margin-bottom: 15px;">
                <label for="template_subject">
                    <?php esc_html_e('Subject/Title', 'sandcrime'); ?>
                </label>
                <input type="text" 
                       id="template_subject" 
                       name="template_subject" 
                       value="<?php echo esc_attr(isset($template_data['subject']) ? $template_data['subject'] : ''); ?>"
                       class="large-text">
            </div>

            <div class="template-body">
                <label for="template_content">
                    <?php esc_html_e('Content', 'sandcrime'); ?>
                </label>
                <?php
                wp_editor(
                    isset($template_data['content']) ? $template_data['content'] : '',
                    'template_content',
                    array(
                        'media_buttons' => true,
                        'textarea_name' => 'template_content',
                        'textarea_rows' => 15,
                        'teeny' => false
                    )
                );
                ?>
            </div>

            <div class="template-options" style="margin-top: 15px;">
                <fieldset>
                    <legend><?php esc_html_e('Template Options', 'sandcrime'); ?></legend>
                    
                    <label>
                        <input type="checkbox" 
                               name="template_options[track_opens]" 
                               value="1"
                               <?php checked(isset($template_data['options']['track_opens'])); ?>>
                        <?php esc_html_e('Track Opens (Email only)', 'sandcrime'); ?>
                    </label>
                    
                    <label>
                        <input type="checkbox" 
                               name="template_options[track_clicks]" 
                               value="1"
                               <?php checked(isset($template_data['options']['track_clicks'])); ?>>
                        <?php esc_html_e('Track Clicks (Email only)', 'sandcrime'); ?>
                    </label>
                </fieldset>
            </div>
        </div>
        <?php
    }

    public function render_preview_meta_box($post) {
        ?>
        <div class="template-preview-wrapper">
            <div class="preview-controls">
                <select id="preview_type">
                    <option value="email"><?php esc_html_e('Email Preview', 'sandcrime'); ?></option>
                    <option value="push"><?php esc_html_e('Push Preview', 'sandcrime'); ?></option>
                    <option value="sms"><?php esc_html_e('SMS Preview', 'sandcrime'); ?></option>
                </select>
                <button type="button" class="button" id="refresh_preview">
                    <?php esc_html_e('Refresh Preview', 'sandcrime'); ?>
                </button>
            </div>
            <div class="preview-content">
                <div id="preview_container">
                    <p class="description">
                        <?php esc_html_e('Save the template to see the preview.', 'sandcrime'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_placeholders_meta_box($post) {
        ?>
        <div class="template-placeholders-wrapper">
            <p><?php esc_html_e('Available placeholders for use in your template:', 'sandcrime'); ?></p>
            <ul class="placeholder-list">
                <li><code>{{recipient_name}}</code> - <?php esc_html_e('Recipient\'s name', 'sandcrime'); ?></li>
                <li><code>{{recipient_email}}</code> - <?php esc_html_e('Recipient\'s email', 'sandcrime'); ?></li>
                <li><code>{{site_name}}</code> - <?php esc_html_e('Website name', 'sandcrime'); ?></li>
                <li><code>{{site_url}}</code> - <?php esc_html_e('Website URL', 'sandcrime'); ?></li>
                <li><code>{{date}}</code> - <?php esc_html_e('Current date', 'sandcrime'); ?></li>
                <li><code>{{time}}</code> - <?php esc_html_e('Current time', 'sandcrime'); ?></li>
            </ul>
            <p class="description">
                <?php esc_html_e('Use these placeholders in your template content. They will be replaced with actual values when the notification is sent.', 'sandcrime'); ?>
            </p>
        </div>
        <?php
    }

    public function save_template($post_id, $post) {
        if (!isset($_POST['template_nonce']) || 
            !wp_verify_nonce($_POST['template_nonce'], 'sandcrime_template_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'notification_template') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $template_data = array(
            'type' => sanitize_key($_POST['template_type']),
            'category' => sanitize_key($_POST['template_category']),
            'description' => sanitize_textarea_field($_POST['template_description']),
            'subject' => sanitize_text_field($_POST['template_subject']),
            'content' => wp_kses_post($_POST['template_content']),
            'options' => array(
                'track_opens' => isset($_POST['template_options']['track_opens']),
                'track_clicks' => isset($_POST['template_options']['track_clicks'])
            ),
            'updated_at' => current_time('mysql'),
            'updated_by' => get_current_user_id()
        );

        update_post_meta($post_id, '_template_data', $template_data);
    }

    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['type'] = __('Type', 'sandcrime');
        $new_columns['category'] = __('Category', 'sandcrime');
        $new_columns['last_used'] = __('Last Used', 'sandcrime');
        $new_columns['usage_count'] = __('Usage Count', 'sandcrime');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    public function custom_column_content($column, $post_id) {
        $template_data = get_post_meta($post_id, '_template_data', true);

        switch ($column) {
            case 'type':
                $types = array(
                    'email' => __('Email', 'sandcrime'),
                    'push' => __('Push', 'sandcrime'),
                    'sms' => __('SMS', 'sandcrime')
                );
                echo isset($types[$template_data['type']]) ? 
                    esc_html($types[$template_data['type']]) : 
                    '—';
                break;

            case 'category':
                $categories = array(
                    'alert' => __('Alert', 'sandcrime'),
                    'notification' => __('Notification', 'sandcrime'),
                    'reminder' => __('Reminder', 'sandcrime'),
                    'update' => __('Update', 'sandcrime'),
                    'report' => __('Report', 'sandcrime')
                );
                echo isset($categories[$template_data['category']]) ? 
                    esc_html($categories[$template_data['category']]) : 
                    '—';
                break;

            case 'last_used':
                $last_used = get_post_meta($post_id, '_last_used', true);
                echo $last_used ? 
                    esc_html(human_time_diff(strtotime($last_used)) . ' ' . __('ago', 'sandcrime')) : 
                    '—';
                break;

            case 'usage_count':
                echo esc_html(number_format_i18n(
                    (int) get_post_meta($post_id, '_usage_count', true)
                ));
                break;
        }
    }

    public function set_sortable_columns($columns) {
        $columns['type'] = 'type';
        $columns['category'] = 'category';
        $columns['last_used'] = 'last_used';
        $columns['usage_count'] = 'usage_count';
        return $columns;
    }
}

// Initialize templates
new SandCrime_Notification_Templates();