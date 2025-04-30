class NotificationTemplates {
    constructor() {
        this.currentTemplateId = null;
        this.editor = null;
        this.hasUnsavedChanges = false;

        this.initializeComponents();
        this.bindEvents();
    }

    initializeComponents() {
        // Initialize modals
        this.templateModal = new Modal(document.getElementById('template-editor'));
        this.previewModal = new Modal(document.getElementById('preview-modal'));
        this.variablesModal = new Modal(document.getElementById('variables-modal'));

        // Initialize rich text editor if not in code view mode
        if (document.getElementById('template_content')) {
            this.initializeEditor();
        }

        // Initialize select2 for filters
        jQuery('#template-type-filter, #template-status-filter').select2({
            minimumResultsForSearch: Infinity,
            width: '200px'
        });
    }

    initializeEditor() {
        // Store reference to tinyMCE editor when it's ready
        if (typeof tinyMCE !== 'undefined') {
            tinyMCE.on('AddEditor', (e) => {
                if (e.editor.id === 'template_content') {
                    this.editor = e.editor;
                    this.setupEditorEvents();
                }
            });
        }
    }

    setupEditorEvents() {
        this.editor.on('change', () => {
            this.hasUnsavedChanges = true;
        });

        this.editor.on('keydown', (e) => {
            // Handle tab key for better accessibility
            if (e.keyCode === 9) {
                e.preventDefault();
                this.editor.execCommand('mceInsertContent', false, '    ');
            }
        });
    }

    bindEvents() {
        // Template list actions
        document.getElementById('add-new-template')
            .addEventListener('click', (e) => this.showTemplateEditor());

        document.getElementById('template-list')
            .addEventListener('click', (e) => this.handleListActions(e));

        // Template editor actions
        document.getElementById('save-template')
            .addEventListener('click', () => this.saveTemplate());

        document.getElementById('cancel-template')
            .addEventListener('click', () => this.closeTemplateEditor());

        // Filter actions
        document.getElementById('template-type-filter')
            .addEventListener('change', () => this.filterTemplates());

        document.getElementById('template-status-filter')
            .addEventListener('change', () => this.filterTemplates());

        document.getElementById('template-search')
            .addEventListener('input', this.debounce(() => this.filterTemplates(), 300));

        // Variable insertion
        document.querySelector('.insert-variable')
            .addEventListener('click', () => this.showVariablesModal());

        document.querySelectorAll('.insert-var-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.insertVariable(e));
        });

        // Preview handling
        document.querySelector('.preview-template')
            .addEventListener('click', () => this.showPreview());

        // Unsaved changes warning
        window.addEventListener('beforeunload', (e) => this.handleUnsavedChanges(e));
    }

    async showTemplateEditor(templateId = null) {
        this.currentTemplateId = templateId;
        const form = document.getElementById('template-form');

        if (templateId) {
            try {
                const template = await this.loadTemplate(templateId);
                this.populateForm(template);
            } catch (error) {
                this.showError('Failed to load template');
                return;
            }
        } else {
            form.reset();
            if (this.editor) {
                this.editor.setContent('');
            }
        }

        this.templateModal.show();
        this.hasUnsavedChanges = false;
    }

    async loadTemplate(templateId) {
        const response = await fetch(sandcrimeTemplates.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_notification_template',
                nonce: sandcrimeTemplates.nonce,
                template_id: templateId
            })
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.data);
        }

        return data.data;
    }

    populateForm(template) {
        const form = document.getElementById('template-form');
        form.elements.template_id.value = template.id;
        form.elements.name.value = template.name;
        form.elements.type.value = template.type;
        form.elements.description.value = template.description;
        form.elements.status.checked = template.status === 'active';

        if (this.editor) {
            this.editor.setContent(template.content);
        } else {
            form.elements.template_content.value = template.content;
        }
    }

    async saveTemplate() {
        const form = document.getElementById('template-form');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        formData.append('action', 'save_notification_template');
        formData.append('nonce', sandcrimeTemplates.nonce);

        if (this.editor) {
            formData.set('content', this.editor.getContent());
        }

        try {
            const response = await fetch(sandcrimeTemplates.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.hasUnsavedChanges = false;
                this.templateModal.hide();
                this.refreshTemplateList();
                this.showSuccess('Template saved successfully');
            } else {
                throw new Error(data.data);
            }
        } catch (error) {
            this.showError('Failed to save template: ' + error.message);
        }
    }

    async showPreview() {
        const content = this.editor ? 
            this.editor.getContent() : 
            document.getElementById('template_content').value;

        const type = document.getElementById('template_type').value;

        try {
            const response = await fetch(sandcrimeTemplates.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_template_preview',
                    nonce: sandcrimeTemplates.nonce,
                    content: content,
                    type: type
                })
            });

            const data = await response.json();
            if (data.success) {
                document.querySelector('.preview-container').innerHTML = data.data.preview;
                this.previewModal.show();
            } else {
                throw new Error(data.data);
            }
        } catch (error) {
            this.showError('Failed to generate preview: ' + error.message);
        }
    }

    showVariablesModal() {
        this.variablesModal.show();
    }

    insertVariable(event) {
        const variable = event.target.dataset.variable;
        if (this.editor) {
            this.editor.insertContent(variable);
        } else {
            const textarea = document.getElementById('template_content');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            textarea.value = textarea.value.substring(0, start) + 
                           variable + 
                           textarea.value.substring(end);
        }
        this.variablesModal.hide();
    }

    async filterTemplates() {
        const type = document.getElementById('template-type-filter').value;
        const status = document.getElementById('template-status-filter').value;
        const search = document.getElementById('template-search').value;

        try {
            const response = await fetch(sandcrimeTemplates.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'filter_notification_templates',
                    nonce: sandcrimeTemplates.nonce,
                    type: type,
                    status: status,
                    search: search
                })
            });

            const data = await response.json();
            if (data.success) {
                this.updateTemplateList(data.data);
            } else {
                throw new Error(data.data);
            }
        } catch (error) {
            this.showError('Failed to filter templates: ' + error.message);
        }
    }

    updateTemplateList(templates) {
        const tbody = document.getElementById('template-list');
        tbody.innerHTML = templates.map(template => this.getTemplateRow(template)).join('');
    }

    getTemplateRow(template) {
        return `
            <tr data-template-id="${template.id}">
                <td class="column-name">
                    <strong>
                        <a href="#" class="edit-template">${template.name}</a>
                    </strong>
                </td>
                <td class="column-type">${template.type}</td>
                <td class="column-description">${template.description}</td>
                <td class="column-status">
                    <span class="status-badge status-${template.status}">
                        ${template.status.charAt(0).toUpperCase() + template.status.slice(1)}
                    </span>
                </td>
                <td class="column-updated">${template.updated_at}</td>
                <td class="column-actions">
                    <div class="row-actions">
                        <span class="edit">
                            <a href="#" class="edit-template">Edit</a> |
                        </span>
                        <span class="duplicate">
                            <a href="#" class="duplicate-template">Duplicate</a> |
                        </span>
                        <span class="preview">
                            <a href="#" class="preview-template">Preview</a> |
                        </span>
                        <span class="delete">
                            <a href="#" class="delete-template">Delete</a>
                        </span>
                    </div>
                </td>
            </tr>
        `;
    }

    handleListActions(event) {
        event.preventDefault();
        const action = event.target.closest('a');
        if (!action) return;

        const row = action.closest('tr');
        const templateId = row.dataset.templateId;

        if (action.classList.contains('edit-template')) {
            this.showTemplateEditor(templateId);
        } else if (action.classList.contains('duplicate-template')) {
            this.duplicateTemplate(templateId);
        } else if (action.classList.contains('preview-template')) {
            this.previewTemplate(templateId);
        } else if (action.classList.contains('delete-template')) {
            this.deleteTemplate(templateId);
        }
    }

    async duplicateTemplate(templateId) {
        if (!confirm(sandcrimeTemplates.i18n.confirmDuplicate)) {
            return;
        }

        try {
            const response = await fetch(sandcrimeTemplates.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'duplicate_notification_template',
                    nonce: sandcrimeTemplates.nonce,
                    template_id: templateId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.refreshTemplateList();
                this.showSuccess('Template duplicated successfully');
            } else {
                throw new Error(data.data);
            }
        } catch (error) {
            this.showError('Failed to duplicate template: ' + error.message);
        }
    }

    async deleteTemplate(templateId) {
        if (!confirm(sandcrimeTemplates.i18n.confirmDelete)) {
            return;
        }

        try {
            const response = await fetch(sandcrimeTemplates.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_notification_template',
                    nonce: sandcrimeTemplates.nonce,
                    template_id: templateId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.refreshTemplateList();
                this.showSuccess('Template deleted successfully');
            } else {
                throw new Error(data.data);
            }
        } catch (error) {
            this.showError('Failed to delete template: ' + error.message);
        }
    }

    refreshTemplateList() {
        this.filterTemplates();
    }

    handleUnsavedChanges(event) {
        if (this.hasUnsavedChanges) {
            event.preventDefault();
            event.returnValue = sandcrimeTemplates.i18n.unsavedChanges;
            return event.returnValue;
        }
    }

    showSuccess(message) {
        this.showNotice(message, 'success');
    }

    showError(message) {
        this.showNotice(message, 'error');
    }

    showNotice(message, type) {
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${message}</p>`;

        const wrapper = document.querySelector('.wrap');
        wrapper.insertBefore(notice, wrapper.firstChild);

        setTimeout(() => {
            notice.remove();
        }, 5000);
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize templates system when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationTemplates = new NotificationTemplates();
});