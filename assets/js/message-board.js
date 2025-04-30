        // ... continued from previous implementation ...

        sendMessage($form) {
            const formData = new FormData($form[0]);
            formData.append('action', 'send_group_message');
            formData.append('nonce', sandcrimeGroups.nonce);
            formData.append('group_id', sandcrimeGroups.groupId);

            // Add file attachments
            const fileInput = document.getElementById('file-input');
            if (fileInput.files.length > 0) {
                Array.from(fileInput.files).forEach((file, index) => {
                    formData.append(`attachments[${index}]`, file);
                });
            }

            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: () => {
                    $form.find('button[type="submit"]').prop('disabled', true).text('Sending...');
                },
                success: (response) => {
                    if (response.success) {
                        this.closeComposeModal();
                        this.resetAndReload();
                        this.showNotification('Message sent successfully', 'success');
                    } else {
                        this.showNotification('Failed to send message', 'error');
                    }
                },
                complete: () => {
                    $form.find('button[type="submit"]')
                        .prop('disabled', false)
                        .text('Send Message');
                }
            });
        }

        initializeRealTimeUpdates() {
            if (typeof Pusher === 'undefined') return;

            const channel = window.notificationManager.channel;
            
            channel.bind('group.message', (data) => {
                if (data.group_id === sandcrimeGroups.groupId) {
                    this.handleNewMessage(data);
                }
            });
        }

        handleNewMessage(data) {
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'GET',
                data: {
                    action: 'get_message_details',
                    nonce: sandcrimeGroups.nonce,
                    message_id: data.message_id
                },
                success: (response) => {
                    if (response.success) {
                        const $list = $('#message-list');
                        $list.prepend(this.createMessageElement(response.data));
                        this.updateUnreadCount();
                        
                        // Show notification if not composed by current user
                        if (response.data.user_id !== sandcrimeGroups.currentUserId) {
                            this.showNotification('New message received', 'info');
                        }
                    }
                }
            });
        }

        resetAndReload() {
            this.offset = 0;
            this.hasMore = true;
            $('#load-more').show();
            this.loadMessages();
        }

        showComposeModal() {
            $('#compose-modal').show();
            $('#compose-form')[0].reset();
            $('#attachment-list').empty();
        }

        closeComposeModal() {
            $('#compose-modal').hide();
            $('#compose-form')[0].reset();
            $('#attachment-list').empty();
        }

        updateUnreadCount() {
            const unreadCount = $('.message-item.unread').length;
            const $badge = $('#unread-count');
            
            if (unreadCount > 0) {
                $badge.text(unreadCount).show();
            } else {
                $badge.hide();
            }
        }

        showNotification(message, type) {
            const $notification = $(`
                <div class="notification notification-${type}">
                    ${message}
                    <button class="close-notification">&times;</button>
                </div>
            `);

            $('.message-board-wrapper').prepend($notification);

            setTimeout(() => {
                $notification.fadeOut(() => {
                    $notification.remove();
                });
            }, 5000);

            $notification.find('.close-notification').on('click', () => {
                $notification.remove();
            });
        }

        formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) { // less than 1 minute
                return 'Just now';
            } else if (diff < 3600000) { // less than 1 hour
                const minutes = Math.floor(diff / 60000);
                return `${minutes}m ago`;
            } else if (diff < 86400000) { // less than 1 day
                const hours = Math.floor(diff / 3600000);
                return `${hours}h ago`;
            } else if (diff < 604800000) { // less than 1 week
                const days = Math.floor(diff / 86400000);
                return `${days}d ago`;
            } else {
                return date.toLocaleDateString() + ' ' + 
                       date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        getFileName(url) {
            return url.split('/').pop();
        }

        capitalizeFirst(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
    }

    // Initialize message board when document is ready
    $(document).ready(() => {
        if ($('.message-board-wrapper').length) {
            window.messageBoard = new MessageBoard();
        }
    });

})(jQuery);