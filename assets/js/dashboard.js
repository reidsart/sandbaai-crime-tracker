(function($) {
    'use strict';

    class DashboardManager {
        constructor() {
            this.initializeEventListeners();
            this.initializeNotificationsPanel();
            this.initializeReportHandlers();
        }

        initializeEventListeners() {
            // Notification panel toggle
            $('#toggle-notifications-panel').on('click', (e) => {
                e.preventDefault();
                this.toggleNotificationsPanel();
            });

            // Close notification panel
            $('.close-panel').on('click', () => {
                this.closeNotificationsPanel();
            });

            // Close panel on outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.notifications-panel, #toggle-notifications-panel').length) {
                    this.closeNotificationsPanel();
                }
            });

            // View report details
            $('.view-report').on('click', (e) => {
                const reportId = $(e.currentTarget).data('id');
                this.loadReportDetails(reportId);
            });

            // Edit report
            $('.edit-report').on('click', (e) => {
                const reportId = $(e.currentTarget).data('id');
                this.editReport(reportId);
            });

            // View group details
            $('.view-group').on('click', (e) => {
                const groupId = $(e.currentTarget).data('id');
                this.loadGroupDetails(groupId);
            });

            // Close modals
            $('.close-modal').on('click', () => {
                $('.modal').hide();
            });
        }

        initializeNotificationsPanel() {
            // Handle notification settings form submission
            $('.notification-settings-form').on('submit', (e) => {
                e.preventDefault();
                this.saveNotificationSettings($(e.currentTarget));
            });
        }

        initializeReportHandlers() {
            // Initialize report filters if they exist
            if ($('.report-filters').length) {
                this.initializeFilters();
            }

            // Initialize report pagination
            this.initializePagination();
        }

        toggleNotificationsPanel() {
            $('.notifications-panel').toggleClass('active');
        }

        closeNotificationsPanel() {
            $('.notifications-panel').removeClass('active');
        }

        loadReportDetails(reportId) {
            $.ajax({
                url: sandcrimeDashboard.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_report_details',
                    nonce: sandcrimeDashboard.nonce,
                    report_id: reportId
                },
                success: (response) => {
                    if (response.success) {
                        $('#report-details').html(response.data);
                        $('#report-modal').show();
                    } else {
                        this.showNotification('Error loading report details', 'error');
                    }
                },
                error: () => {
                    this.showNotification('Server error occurred', 'error');
                }
            });
        }

        editReport(reportId) {
            window.location.href = `${window.location.origin}/edit-report/?id=${reportId}`;
        }

        loadGroupDetails(groupId) {
            $.ajax({
                url: sandcrimeDashboard.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_group_details',
                    nonce: sandcrimeDashboard.nonce,
                    group_id: groupId
                },
                success: (response) => {
                    if (response.success) {
                        $('#report-details').html(response.data);
                        $('#report-modal').show();
                    } else {
                        this.showNotification('Error loading group details', 'error');
                    }
                },
                error: () => {
                    this.showNotification('Server error occurred', 'error');
                }
            });
        }

        saveNotificationSettings($form) {
            const formData = new FormData($form[0]);
            formData.append('action', 'save_notification_settings');
            formData.append('nonce', sandcrimeDashboard.nonce);

            $.ajax({
                url: sandcrimeDashboard.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Settings saved successfully', 'success');
                    } else {
                        this.showNotification('Error saving settings', 'error');
                    }
                },
                error: () => {
                    this.showNotification('Server error occurred', 'error');
                }
            });
        }

        initializeFilters() {
            $('.report-filters select').on('change', () => {
                this.applyFilters();
            });

            $('.report-filters input[type="date"]').on('change', () => {
                this.applyFilters();
            });
        }

        applyFilters() {
            const filters = {
                category: $('#filter-category').val(),
                status: $('#filter-status').val(),
                date_start: $('#filter-date-start').val(),
                date_end: $('#filter-date-end').val()
            };

            this.loadFilteredReports(filters);
        }

        loadFilteredReports(filters) {
            $.ajax({
                url: sandcrimeDashboard.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_filtered_reports',
                    nonce: sandcrimeDashboard.nonce,
                    ...filters
                },
                success: (response) => {
                    if (response.success) {
                        $('.reports-list').html(response.data.html);
                        this.updatePagination(response.data.total_pages);
                    }
                }
            });
        }

        initializePagination() {
            $(document).on('click', '.reports-pagination a', (e) => {
                e.preventDefault();
                const page = $(e.currentTarget).data('page');
                this.loadPage(page);
            });
        }

        loadPage(page) {
            const filters = {
                category: $('#filter-category').val(),
                status: $('#filter-status').val(),
                date_start: $('#filter-date-start').val(),
                date_end: $('#filter-date-end').val(),
                page: page
            };

            this.loadFilteredReports(filters);
        }

        showNotification(message, type = 'success') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.dashboard-notices').append($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            }, 5000);

            // Handle manual dismiss
            $notice.find('.notice-dismiss').on('click', () => {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            });
        }
    }

    // Initialize dashboard when document is ready
    $(document).ready(() => {
        new DashboardManager();
    });

})(jQuery);