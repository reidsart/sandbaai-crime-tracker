<?php if (!defined('ABSPATH')) exit; ?>

<div class="message-board-wrapper">
    <div class="message-board-header">
        <div class="header-title">
            <h2>Group Messages</h2>
            <span class="unread-count" id="unread-count"></span>
        </div>
        
        <?php if (SandCrime_Group_Messages::can_send_message($group_id)): ?>
            <button class="button compose-message" id="compose-message">
                <i class="dashicons dashicons-edit"></i> New Message
            </button>
        <?php endif; ?>
    </div>

    <div class="message-filters">
        <div class="filter-group">
            <label>Type:</label>
            <select id="message-type-filter">
                <option value="">All Types</option>
                <option value="announcement">Announcements</option>
                <option value="alert">Alerts</option>
                <option value="general">General</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Priority:</label>
            <select id="priority-filter">
                <option value="">All Priorities</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="normal">Normal</option>
                <option value="low">Low</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Date Range:</label>
            <input type="date" id="date-from" name="date-from">
            <span>to</span>
            <input type="date" id="date-to" name="date-to">
        </div>
    </div>

    <div class="message-list" id="message-list">
        <!-- Messages will be loaded here -->
        <div class="loading-messages">Loading messages...</div>
    </div>

    <div class="load-more-wrapper">
        <button class="button load-more" id="load-more">Load More</button>
    </div>

    <!-- Compose Message Modal -->
    <div id="compose-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Message</h3>
                <button class="close-modal">&times;</button>
            </div>
            
            <form id="compose-form">
                <div class="form-row">
                    <label>Message Type:</label>
                    <select name="message_type" required>
                        <option value="general">General Message</option>
                        <option value="announcement">Announcement</option>
                        <option value="alert">Alert</option>
                    </select>
                </div>

                <div class="form-row">
                    <label>Priority:</label>
                    <select name="priority" required>
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <div class="form-row">
                    <label>Message:</label>
                    <textarea name="message" rows="5" required></textarea>
                </div>

                <div class="form-row">
                    <label>Expires:</label>
                    <input type="datetime-local" name="expires_at">
                    <span class="help-text">Leave blank if message doesn't expire</span>
                </div>

                <div class="form-row">
                    <label>Attachments:</label>
                    <div class="attachment-zone" id="attachment-zone">
                        <div class="dropzone-text">
                            Drop files here or click to upload
                        </div>
                        <input type="file" multiple id="file-input" name="attachments[]">
                    </div>
                    <div class="attachment-list" id="attachment-list"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">Send Message</button>
                    <button type="button" class="button cancel-compose">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.message-board-wrapper {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}
.message-board-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.header-title {
    display: flex;
    align-items: center;
    gap: 10px;
}
.unread-count {
    background: #e53935;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    display: none;
}
.message-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}
.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.filter-group label {
    font-weight: 500;
}
.message-list {
    border: 1px solid #ddd;
    border-radius: 4px;
    min-height: 200px;
}
.message-item {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    position: relative;
}
.message-item:last-child {
    border-bottom: none;
}
.message-item.unread {
    background: #f8f9fa;
}
.message-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #2271b1;
}
.message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}
.message-meta {
    display: flex;
    gap: 15px;
    color: #666;
    font-size: 14px;
}
.message-type {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
.type-announcement { background: #e3f2fd; color: #0d47a1; }
.type-alert { background: #ffebee; color: #c62828; }
.type-general { background: #f5f5f5; color: #333; }
.priority-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
.priority-urgent { background: #ffebee; color: #c62828; }
.priority-high { background: #fff3e0; color: #e65100; }
.priority-normal { background: #e8f5e9; color: #2e7d32; }
.priority-low { background: #f5f5f5; color: #666; }
.message-content {
    margin-bottom: 10px;
    white-space: pre-wrap;
}
.message-attachments {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}
.attachment-item {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: #f5f5f5;
    border-radius: 3px;
    font-size: 14px;
}
.read-status {
    font-size: 12px;
    color: #666;
}
.attachment-zone {
    border: 2px dashed #ddd;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    position: relative;
}
.attachment-zone.dragover {
    border-color: #2271b1;
    background: #f8f9fa;
}
.attachment-zone input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}
.attachment-list {
    margin-top: 10px;
}
.attachment-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px;
    background: #f5f5f5;
    border-radius: 3px;
    margin-bottom: 5px;
}
.attachment-preview .remove-attachment {
    color: #dc3545;
    cursor: pointer;
}
.loading-messages {
    padding: 20px;
    text-align: center;
    color: #666;
}
.load-more-wrapper {
    text-align: center;
    margin-top: 20px;
}
@media (max-width: 768px) {
    .message-filters {
        flex-direction: column;
        gap: 10px;
    }
    .filter-group {
        flex-wrap: wrap;
    }
}
</style>