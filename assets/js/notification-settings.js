class NotificationSettings {
    constructor() {
        this.initializeComponents();
        this.bindEvents();
        this.setupVapidKeyGeneration();
        this.setupTestNotifications();
    }

    initializeComponents() {
        // Initialize multiselect
        jQuery('.sandcrime-multiselect').select2({
            width: '400px',
            placeholder: 'Select options',
            closeOnSelect: false,
            allowClear: true
        });

        // Initialize tooltips
        jQuery('[data-tooltip]').tooltip();

        // Initialize CodeMirror for email template if exists
        const emailTemplate = document.getElementById('email_template');
        if (emailTemplate) {
            this.emailEditor = CodeMirror.fromTextArea(emailTemplate, {
                mode: 'html',
                lineNumbers: true,
                theme: 'monokai',
                autoCloseTags: true,
                autoCloseBrackets: true,
                matchBrackets: true,
                lineWrapping: true,
                height: '400px'
            });
        }
    }

    bindEvents() {
        // Save settings validation
        jQuery('#sandcrime-settings-form').on('submit', (e) => {
            if (!this.validateSettings()) {
                e.preventDefault();
            }
        });

        // Dynamic field dependencies
        jQuery('#enable_notifications').on('change', (e) => {
            this.toggleDependentFields(e.target.checked);
        });

        // Queue settings interactions
        jQuery('#queue_batch_size, #queue_retry_limit').on('change', (e) => {
            this.validateQueueSettings(e.target);
        });

        // Real-time template preview
        if (this.emailEditor) {
            this.emailEditor.on('change', () => {
                this.updateTemplatePreview();
            });
        }
    }

    validateSettings() {
        let isValid = true;

        // Validate VAPID keys if push notifications are enabled
        if (jQuery('#enable_push_notifications').is(':checked')) {
            const publicKey = jQuery('#vapid_public_key').val();
            const privateKey = jQuery('#vapid_private_key').val();

            if (!publicKey || !privateKey) {
                this.showError('VAPID keys are required for push notifications');
                isValid = false;
            }
        }

        // Validate email template
        if (this.emailEditor) {
            const template = this.emailEditor.getValue();
            if (!template.includes('{{content}}')) {
                this.showError('Email template must include {{content}} placeholder');
                isValid = false;
            }
        }

        // Validate batch sizes
        const batchSize = parseInt(jQuery('#queue_batch_size').val());
        if (isNaN(batchSize) || batchSize < 1 || batchSize > 1000) {
            this.showError('Queue batch size must be between 1 and 1000');
            isValid = false;
        }

        return isValid;
    }

    toggleDependentFields(enabled) {
        const dependentFields = [
            '#notification_types',
            '#email_template',
            '#email_batch_size',
            '#queue_batch_size',
            '#queue_retry_limit'
        ];

        dependentFields.forEach(selector => {
            jQuery(selector).closest('tr').toggle(enabled);
        });
    }

    validateQueueSettings(input) {
        const value = parseInt(input.value);
        const min = parseInt(input.getAttribute('min'));
        const max = parseInt(input.getAttribute('max'));

        if (value < min || value > max) {
            input.setCustomValidity(`Value must be between ${min} and ${max}`);
        } else {
            input.setCustomValidity('');
        }
    }

    updateTemplatePreview() {
        const template = this.emailEditor.getValue();
        const previewContent = 'Sample notification content';
        const preview = template.replace('{{content}}', previewContent);

        jQuery('#template-preview').html(preview);
    }

    setupVapidKeyGeneration() {
        jQuery('#generate-vapid-keys').on('click', async (e) => {
            e.preventDefault();
            
            try {
                const response = await this.makeRequest('generate_vapid_keys');
                if (response.success) {
                    jQuery('#vapid_public_key').val(response.data.publicKey);
                    jQuery('#vapid_private_key').val(response.data.privateKey);
                    this.showSuccess('VAPID keys generated successfully');
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                this.showError('Failed to generate VAPID keys: ' + error.message);
            }
        });
    }

    setupTestNotifications() {
        jQuery('#test-notification').on('click', async (e) => {
            e.preventDefault();
            
            const type = jQuery('#test-notification-type').val();
            
            try {
                const response = await this.makeRequest('test_notification_delivery', {
                    type: type
                });

                if (response.success) {
                    this.showSuccess(sandcrimeSettings.i18n.testSuccess);
                } else {
                    throw new Error(response.data);
                }
            } catch (error) {
                this.showError(sandcrimeSettings.i18n.testError + ': ' + error.message);
            }
        });
    }

    async makeRequest(action, data = {}) {
        try {
            const response = await fetch(sandcrimeSettings.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'sandcrime_' + action,
                    nonce: sandcrimeSettings.nonce,
                    ...data
                })
            });

            return await response.json();
        } catch (error) {
            console.error('Request failed:', error);
            throw error;
        }
    }

    showSuccess(message) {
        this.showNotice(message, 'success');
    }

    showError(message) {
        this.showNotice(message, 'error');
    }

    showNotice(message, type = 'info') {
        const notice = jQuery('<div>', {
            class: `notice notice-${type} is-dismissible`,
            html: `<p>${message}</p>`
        });

        const wrapper = jQuery('.wrap').first();
        wrapper.find('.notice').remove();
        wrapper.prepend(notice);

        // Initialize WordPress dismissible notices
        jQuery(document).trigger('wp-updates-notice-added');
    }
}

// Initialize settings when document is ready
jQuery(document).ready(() => {
    window.notificationSettings = new NotificationSettings();
});

// Add confirmation for unsaved changes
jQuery(window).on('beforeunload', (e) => {
    if (window.notificationSettings && window.notificationSettings.hasUnsavedChanges()) {
        e.preventDefault();
        return '';
    }
});