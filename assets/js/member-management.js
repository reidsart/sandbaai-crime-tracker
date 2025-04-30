(function($) {
    'use strict';

    class MemberManager {
        constructor() {
            this.searchTimeout = null;
            this.selectedUser = null;
            
            this.initializeEventListeners();
        }

        initializeEventListeners() {
            // Member search
            $('#member-search').on('input', (e) => {
                this.filterMembers(e.target.value);
            });

            // Role filter
            $('#role-filter').on('change', (e) => {
                this.filterByRole(e.target.value);
            });

            // Role change
            $('.role-select').on('change', (e) => {
                const userId = $(e.target).data('user-id');
                const newRole = e.target.value;
                this.updateMemberRole(userId, newRole);
            });

            // Remove member
            $('.remove-member').on('click', (e) => {
                const userId = $(e.currentTarget).data('user-id');
                this.removeMember(userId);
            });

            // Add member modal
            $('#add-member-btn').on('click', () => {
                $('#add-member-modal').show();
            });

            // User search
            $('#user-search').on('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.searchUsers(e.target.value);
                }, 300);
            });

            // Add member form
            $('#add-member-form').on('submit', (e) => {
                e.preventDefault();
                if (this.selectedUser) {
                    this.addMember(
                        this.selectedUser.id,
                        $('#member-role').val()
                    );
                }
            });

            // Close modal
            $('.close-modal, .cancel-add').on('click', () => {
                this.closeAddMemberModal();
            });
        }

        filterMembers(query) {
            query = query.toLowerCase();
            $('.member-item').each(function() {
                const name = $(this).find('.member-name').text().toLowerCase();
                const email = $(this).find('.member-email').text().toLowerCase();
                
                if (name.includes(query) || email.includes(query)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        filterByRole(role) {
            if (role) {
                $('.member-item').hide();
                $(`.member-item .role-select[value="${role}"]`).closest('.member-item').show();
            } else {
                $('.member-item').show();
            }
        }

        updateMemberRole(userId, newRole) {
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'update_member_role',
                    nonce: sandcrimeGroups.nonce,
                    user_id: userId,
                    role: newRole,
                    group_id: sandcrimeGroups.groupId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Member role updated successfully', 'success');
                    } else {
                        this.showNotification('Failed to update member role', 'error');
                        // Reset select to previous value
                        location.reload();
                    }
                }
            });
        }

        removeMember(userId) {
            if (!confirm('Are you sure you want to remove this member?')) {
                return;
            }

            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'remove_group_member',
                    nonce: sandcrimeGroups.nonce,
                    user_id: userId,
                    group_id: sandcrimeGroups.groupId
                },
                success: (response) => {
                    if (response.success) {
                        $(`.member-item[data-id="${userId}"]`).fadeOut(() => {
                            $(this).remove();
                        });
                        this.showNotification('Member removed successfully', 'success');
                    } else {
                        this.showNotification('Failed to remove member', 'error');
                    }
                }
            });
        }

        searchUsers(query) {
            if (query.length < 3) {
                $('#user-search-results').hide();
                return;
            }

            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'search_users',
                    nonce: sandcrimeGroups.nonce,
                    query: query,
                    group_id: sandcrimeGroups.groupId
                },
                success: (response) => {
                    if (response.success) {
                        this.displayUserSearchResults(response.data);
                    }
                }
            });
        }

        displayUserSearchResults(users) {
            const $results = $('#user-search-results');
            $results.empty();

            if (users.length === 0) {
                $results.append('<div class="user-search-item">No users found</div>');
            } else {
                users.forEach(user => {
                    $results.append(`
                        <div class="user-search-item" data-user='${JSON.stringify(user)}'>
                            ${user.avatar}
                            <div class="user-info">
                                <div class="user-name">${user.display_name}</div>
                                <div class="user-email">${user.user_email}</div>
                            </div>
                        </div>
                    `);
                });
            }

            $results.show();

            // Handle user selection
            $('.user-search-item').on('click', (e) => {
                const userData = $(e.currentTarget).data('user');
                this.selectUser(userData);
            });
        }

        selectUser(userData) {
            this.selectedUser = userData;
            $('#user-search').val(userData.display_name);
            $('#user-search-results').hide();
        }

        addMember(userId, role) {
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'add_group_member',
                    nonce: sandcrimeGroups.nonce,
                    user_id: userId,
                    role: role,
                    group_id: sandcrimeGroups.groupId
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        this.showNotification('Failed to add member', 'error');
                    }
                }
            });
        }

        closeAddMemberModal() {
            $('#add-member-modal').hide();
            $('#add-member-form')[0].reset();
            this.selectedUser = null;
            $('#user-search-results').hide();
        }

        showNotification(message, type) {
            const $notification = $(`
                <div class="notification notification-${type}">
                    ${message}
                    <button class="close-notification">&times;</button>
                </div>
            `);

            $('.member-management-wrapper').prepend($notification);

            setTimeout(() => {
                $notification.fadeOut(() => {
                    $notification.remove();
                });
            }, 5000);

            $notification.find('.close-notification').on('click', () => {
                $notification.remove();
            });
        }
    }

    // Initialize member manager when document is ready
    $(document).ready(() => {
        new MemberManager();
    });

})(jQuery);