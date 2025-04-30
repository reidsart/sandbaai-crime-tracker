<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Security_Group_Form {
    private $group;
    private $errors;

    public function __construct($group = null) {
        $this->group = $group;
        $this->errors = new WP_Error();
    }

    public function render() {
        $action = $this->group ? 'edit' : 'create';
        $nonce_action = 'sandcrime_security_group_' . $action;
        ?>
        <div class="wrap">
            <h1><?php echo $this->group ? 
                esc_html__('Edit Security Group', 'sandcrime') : 
                esc_html__('Create Security Group', 'sandcrime'); ?></h1>

            <?php $this->display_errors(); ?>

            <form method="post" id="security-group-form" action="">
                <?php wp_nonce_field($nonce_action, '_wpnonce'); ?>
                
                <input type="hidden" name="action" 
                       value="<?php echo $action; ?>_security_group">
                
                <?php if ($this->group): ?>
                    <input type="hidden" name="group_id" 
                           value="<?php echo esc_attr($this->group->get_id()); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="group_name">
                                <?php esc_html_e('Group Name', 'sandcrime'); ?> <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="group_name" 
                                   id="group_name" 
                                   value="<?php echo esc_attr($this->group ? $this->group->get_name() : ''); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description">
                                <?php esc_html_e('Enter a unique name for this security group.', 'sandcrime'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="group_description">
                                <?php esc_html_e('Description', 'sandcrime'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea name="group_description" 
                                      id="group_description" 
                                      class="large-text" 
                                      rows="3"><?php echo esc_textarea($this->group ? $this->group->get_description() : ''); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Provide a description of this security group\'s purpose.', 'sandcrime'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Notification Settings', 'sandcrime'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <?php esc_html_e('Notification Settings', 'sandcrime'); ?>
                                </legend>
                                
                                <?php
                                $notification_types = array(
                                    'email' => __('Email Notifications', 'sandcrime'),
                                    'push' => __('Push Notifications', 'sandcrime'),
                                    'sms' => __('SMS Notifications', 'sandcrime')
                                );

                                $enabled_types = $this->group ? 
                                    $this->group->get_notification_types() : 
                                    array('email');

                                foreach ($notification_types as $type => $label):
                                    ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="notification_types[]" 
                                               value="<?php echo esc_attr($type); ?>"
                                               <?php checked(in_array($type, $enabled_types)); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                                
                                <p class="description">
                                    <?php esc_html_e('Select the types of notifications this group should receive.', 'sandcrime'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Priority Level', 'sandcrime'); ?>
                        </th>
                        <td>
                            <select name="priority_level" id="priority_level">
                                <?php
                                $priority_levels = array(
                                    'low' => __('Low', 'sandcrime'),
                                    'normal' => __('Normal', 'sandcrime'),
                                    'high' => __('High', 'sandcrime')
                                );

                                $current_priority = $this->group ? 
                                    $this->group->get_priority_level() : 
                                    'normal';

                                foreach ($priority_levels as $value => $label):
                                    ?>
                                    <option value="<?php echo esc_attr($value); ?>"
                                            <?php selected($value, $current_priority); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Set the priority level for notifications sent to this group.', 'sandcrime'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Members', 'sandcrime'); ?>
                        </th>
                        <td>
                            <div class="group-members-wrapper">
                                <select name="group_members[]" 
                                        id="group_members" 
                                        class="sandcrime-user-select" 
                                        multiple="multiple"
                                        style="width: 100%;">
                                    <?php
                                    if ($this->group) {
                                        $members = $this->group->get_members();
                                        foreach ($members as $user_id) {
                                            $user = get_user_by('id', $user_id);
                                            if ($user) {
                                                printf(
                                                    '<option value="%d" selected="selected">%s</option>',
                                                    esc_attr($user_id),
                                                    esc_html($user->display_name)
                                                );
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Select users to add to this security group.', 'sandcrime'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Active Hours', 'sandcrime'); ?>
                        </th>
                        <td>
                            <div class="active-hours-wrapper">
                                <?php
                                $active_hours = $this->group ? 
                                    $this->group->get_active_hours() : 
                                    array(
                                        'enabled' => false,
                                        'schedule' => array()
                                    );
                                ?>
                                <label>
                                    <input type="checkbox" 
                                           name="active_hours_enabled" 
                                           id="active_hours_enabled"
                                           value="1"
                                           <?php checked(!empty($active_hours['enabled'])); ?>>
                                    <?php esc_html_e('Restrict notifications to specific hours', 'sandcrime'); ?>
                                </label>

                                <div class="schedule-grid" 
                                     style="<?php echo empty($active_hours['enabled']) ? 'display: none;' : ''; ?>">
                                    <table class="widefat fixed">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Day', 'sandcrime'); ?></th>
                                                <th><?php esc_html_e('Start Time', 'sandcrime'); ?></th>
                                                <th><?php esc_html_e('End Time', 'sandcrime'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $days = array(
                                                'monday' => __('Monday', 'sandcrime'),
                                                'tuesday' => __('Tuesday', 'sandcrime'),
                                                'wednesday' => __('Wednesday', 'sandcrime'),
                                                'thursday' => __('Thursday', 'sandcrime'),
                                                'friday' => __('Friday', 'sandcrime'),
                                                'saturday' => __('Saturday', 'sandcrime'),
                                                'sunday' => __('Sunday', 'sandcrime')
                                            );

                                            foreach ($days as $day_key => $day_label):
                                                $schedule = isset($active_hours['schedule'][$day_key]) ?
                                                    $active_hours['schedule'][$day_key] :
                                                    array('start' => '09:00', 'end' => '17:00');
                                                ?>
                                                <tr>
                                                    <td><?php echo esc_html($day_label); ?></td>
                                                    <td>
                                                        <input type="time" 
                                                               name="schedule[<?php echo esc_attr($day_key); ?>][start]"
                                                               value="<?php echo esc_attr($schedule['start']); ?>">
                                                    </td>
                                                    <td>
                                                        <input type="time" 
                                                               name="schedule[<?php echo esc_attr($day_key); ?>][end]"
                                                               value="<?php echo esc_attr($schedule['end']); ?>">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" 
                           name="submit" 
                           id="submit" 
                           class="button button-primary" 
                           value="<?php echo $this->group ? 
                               esc_attr__('Update Group', 'sandcrime') : 
                               esc_attr__('Create Group', 'sandcrime'); ?>">
                </p>
            </form>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Initialize Select2 for user selection
                $('.sandcrime-user-select').select2({
                    ajax: {
                        url: ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                q: params.term,
                                action: 'sandcrime_search_users',
                                _wpnonce: '<?php echo wp_create_nonce('sandcrime_search_users'); ?>'
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 2,
                    placeholder: '<?php esc_attr_e('Search for users...', 'sandcrime'); ?>'
                });

                // Toggle active hours schedule
                $('#active_hours_enabled').on('change', function() {
                    $('.schedule-grid').toggle(this.checked);
                });
            });
        </script>
        <?php
    }

    private function display_errors() {
        if ($this->errors->has_errors()) {
            foreach ($this->errors->get_error_messages() as $message) {
                echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }

    public function process() {
        if (!isset($_POST['_wpnonce'])) {
            return false;
        }

        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if (!in_array($action, array('create_security_group', 'edit_security_group'))) {
            return false;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'sandcrime_security_group_' . ($this->group ? 'edit' : 'create'))) {
            $this->errors->add('invalid_nonce', __('Invalid security token. Please try again.', 'sandcrime'));
            return false;
        }

        if (!current_user_can('manage_options')) {
            $this->errors->add('insufficient_permissions', __('You do not have permission to perform this action.', 'sandcrime'));
            return false;
        }

        return $this->save_group();
    }

    private function save_group() {
        $group_data = array(
            'name' => sanitize_text_field($_POST['group_name']),
            'description' => sanitize_textarea_field($_POST['group_description']),
            'notification_types' => isset($_POST['notification_types']) ? 
                array_map('sanitize_text_field', $_POST['notification_types']) : 
                array(),
            'priority_level' => sanitize_text_field($_POST['priority_level']),
            'members' => isset($_POST['group_members']) ? 
                array_map('absint', $_POST['group_members']) : 
                array(),
            'active_hours' => array(
                'enabled' => isset($_POST['active_hours_enabled']),
                'schedule' => $this->sanitize_schedule($_POST['schedule'] ?? array())
            )
        );

        try {
            if ($this->group) {
                $this->group->update($group_data);
                $message = __('Security group updated successfully.', 'sandcrime');
            } else {
                SandCrime_Security_Group::create($group_data);
                $message = __('Security group created successfully.', 'sandcrime');
            }

            add_settings_error(
                'sandcrime_messages',
                'sandcrime_group_updated',
                $message,
                'updated'
            );

            return true;

        } catch (Exception $e) {
            $this->errors->add('save_failed', $e->getMessage());
            return false;
        }
    }

    private function sanitize_schedule($schedule) {
        $sanitized = array();
        
        foreach ($schedule as $day => $times) {
            $day = sanitize_text_field($day);
            if (isset($times['start']) && isset($times['end'])) {
                $sanitized[$day] = array(
                    'start' => sanitize_text_field($times['start']),
                    'end' => sanitize_text_field($times['end'])
                );
            }
        }

        return $sanitized;
    }
}