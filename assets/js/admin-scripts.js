jQuery(document).ready(function ($) {
    // Phone numbers management
    var phoneCounter = $('.phone-number-row').length;

    $('#add-phone-number').on('click', function (e) {
        e.preventDefault();
        phoneCounter++;

        var newRow = `
            <tr class="phone-number-row">
                <td>
                    <input type="text" name="phone_labels[]" class="regular-text" 
                           placeholder="e.g., Main Office, Emergency, etc." required>
                </td>
                <td>
                    <input type="text" name="phone_numbers[]" class="regular-text" 
                           placeholder="e.g., +27 123 456 7890" required>
                </td>
                <td>
                    <button type="button" class="button button-secondary remove-phone">Remove</button>
                    <span class="dashicons dashicons-move sort-handle"></span>
                </td>
            </tr>
        `;

        $('#phone-numbers-table tbody').append(newRow);
    });

    // Remove phone number row
    $(document).on('click', '.remove-phone', function () {
        $(this).closest('tr').remove();
    });

    // Make phone numbers sortable
    if ($.fn.sortable) {
        $('#phone-numbers-table tbody').sortable({
            handle: '.sort-handle',
            axis: 'y'
        });
    }

    // Tab handling
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();
        var target = $(this).attr('href');

        // Update tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Update content
        $('.tab-pane').hide();
        $(target).show();
    });

    // Initialize Select2 for user selection if available
    if ($.fn.select2) {
        $('#new_member').select2({
            width: '350px',
            placeholder: 'Select a user...'
        });
    }
});

// Initialize Select2 for multiple user selection
if ($.fn.select2) {
    $('#new_members').select2({
        width: '350px',
        placeholder: 'Select users...',
        closeOnSelect: false
    });
}

// Handle bulk selection
$('#members-select-all').on('change', function() {
    $('input[name="member_ids[]"]').prop('checked', $(this).prop('checked'));
});

// Handle member role changes
$('.member-role').on('change', function() {
    var userId = $(this).closest('tr').find('input[name="member_ids[]"]').val();
    var newRole = $(this).val();
    
    $.post(ajaxurl, {
        action: 'update_member_role',
        user_id: userId,
        role: newRole,
        group_id: $('input[name="group_id"]').val(),
        nonce: $('#bulk_members_nonce').val()
    });
});

// Confirm member removal
$('.remove-member').on('click', function() {
    if (confirm('Are you sure you want to remove this member?')) {
        var userId = $(this).data('user-id');
        var row = $(this).closest('tr');
        
        $.post(ajaxurl, {
            action: 'remove_group_member',
            user_id: userId,
            group_id: $('input[name="group_id"]').val(),
            nonce: $('#bulk_members_nonce').val()
        }, function(response) {
            if (response.success) {
                row.fadeOut(400, function() { $(this).remove(); });
            }
        });
    }
});

// Security Groups Member Management
jQuery(document).ready(function($) {
    // Initialize Select2 for user search with AJAX
    if ($.fn.select2 && $('#new_members').length) {
        $('#new_members').select2({
            width: '350px',
            placeholder: 'Search users...',
            minimumInputLength: 2,
            multiple: true,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'search_users_for_group',
                        term: params.term,
                        group_id: $('input[name="group_id"]').val(),
                        nonce: $('#search_users_nonce').val()
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.data.results
                    };
                },
                cache: true
            }
        });
    }

    // Handle bulk member actions
    $('.bulk-member-action').on('click', function(e) {
        e.preventDefault();
        
        var action = $(this).data('action');
        var selectedIds = [];
        
        $('input[name="member_ids[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Please select members first.');
            return;
        }
        
        if (!confirm('Are you sure you want to ' + action + ' the selected members?')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'bulk_update_members',
            bulk_action: action,
            member_ids: selectedIds,
            group_id: $('input[name="group_id"]').val(),
            nonce: $('#bulk_members_nonce').val()
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    });

    // Handle member role updates
    $(document).on('change', '.member-role-select', function() {
        var $select = $(this);
        var userId = $select.closest('tr').find('input[name="member_ids[]"]').val();
        
        $.post(ajaxurl, {
            action: 'update_member_role',
            user_id: userId,
            role: $select.val(),
            group_id: $('input[name="group_id"]').val(),
            nonce: $('#bulk_members_nonce').val()
        }, function(response) {
            if (response.success) {
                // Show success indicator
                var $indicator = $('<span class="dashicons dashicons-yes success-indicator"></span>');
                $select.after($indicator);
                setTimeout(function() {
                    $indicator.fadeOut(function() { $(this).remove(); });
                }, 2000);
            }
        });
    });

    // Add new members form submission
    $('#add-members-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var userIds = $('#new_members').val();
        
        if (!userIds || userIds.length === 0) {
            alert('Please select users to add.');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'add_group_members',
            user_ids: userIds,
            role: $form.find('input[name="member_role"]:checked').val(),
            group_id: $('input[name="group_id"]').val(),
            nonce: $('#add_members_nonce').val()
        }, function(response) {
            if (response.success) {
                // Update member list
                $('.current-members').html(response.data.memberListHtml);
                // Reset form
                $('#new_members').val(null).trigger('change');
            }
        });
    });
});

// Settings Page
jQuery(document).ready(function($) {
    // Settings dependencies
    function toggleDependentFields() {
        // reCAPTCHA fields
        var $recaptchaEnabled = $('#enable_recaptcha');
        var $recaptchaFields = $('#recaptcha_site_key, #recaptcha_secret_key').closest('tr');
        
        $recaptchaEnabled.on('change', function() {
            $recaptchaFields.toggle($(this).prop('checked'));
        }).trigger('change');

        // Email notification fields
        var $emailEnabled = $('#enable_email_notifications');
        var $emailFields = $('#notification_emails, [name="settings[notification_types][]"]').closest('tr');
        
        $emailEnabled.on('change', function() {
            $emailFields.toggle($(this).prop('checked'));
        }).trigger('change');

        // WhatsApp fields
        var $whatsappEnabled = $('#enable_whatsapp');
        var $whatsappFields = $('#whatsapp_api_key').closest('tr');
        
        $whatsappEnabled.on('change', function() {
            $whatsappFields.toggle($(this).prop('checked'));
        }).trigger('change');

        // Telegram fields
        var $telegramEnabled = $('#enable_telegram');
        var $telegramFields = $('#telegram_bot_token').closest('tr');
        
        $telegramEnabled.on('change', function() {
            $telegramFields.toggle($(this).prop('checked'));
        }).trigger('change');
    }

    // Initialize dependencies
    toggleDependentFields();

    // Form validation
    $('form').on('submit', function(e) {
        var $form = $(this);
        var isValid = true;
        var errorMessages = [];

        // Validate reCAPTCHA settings
        if ($('#enable_recaptcha').prop('checked')) {
            if (!$('#recaptcha_site_key').val()) {
                errorMessages.push('reCAPTCHA Site Key is required when reCAPTCHA is enabled');
                isValid = false;
            }
            if (!$('#recaptcha_secret_key').val()) {
                errorMessages.push('reCAPTCHA Secret Key is required when reCAPTCHA is enabled');
                isValid = false;
            }
        }

        // Validate email notifications
        if ($('#enable_email_notifications').prop('checked')) {
            var emails = $('#notification_emails').val();
            if (!emails) {
                errorMessages.push('At least one notification email is required when email notifications are enabled');
                isValid = false;
            } else {
                var emailList = emails.split('\n');
                emailList.forEach(function(email) {
                    if (email.trim() && !isValidEmail(email.trim())) {
                        errorMessages.push('Invalid email address: ' + email.trim());
                        isValid = false;
                    }
                });
            }
        }

        // Display errors if any
        if (!isValid) {
            e.preventDefault();
            alert('Please correct the following errors:\n\n' + errorMessages.join('\n'));
        }
    });

    // Helper function to validate email
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
});