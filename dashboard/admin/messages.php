<?php
$page_title = "Messages";
$page_specific_js = "assets/js/messages.js";
$page_specific_css = "assets/css/messages.css";
require_once 'includes/header.php';
?>

<div class="content">
    <div class="messages-container">
        <div class="conversations-sidebar">
            <div class="sidebar-header">
                <h2>Conversations</h2>
                <button id="new-conversation-btn" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> New
                </button>
            </div>
            
            <div class="search-box">
                <input type="text" id="conversation-search" placeholder="Search conversations...">
                <i class="fas fa-search"></i>
            </div>
            
            <ul class="conversation-list" id="conversation-list">
                <!-- Conversations will be loaded dynamically -->
                <li class="loading-placeholder">Loading conversations...</li>
            </ul>
        </div>
        
        <div class="messages-view">
            <div class="empty-state" id="empty-state">
                <i class="fas fa-comments"></i>
                <p>Select a conversation or start a new one</p>
            </div>
            
            <div class="message-content" id="message-content" style="display: none;">
                <div class="message-header">
                    <div class="conversation-info">
                        <h3 id="conversation-title">Conversation Title</h3>
                        <p id="conversation-participants">Participants: </p>
                    </div>
                    <div class="conversation-actions">
                        <button id="add-participant-btn" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-user-plus"></i>
                        </button>
                        <button id="view-info-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                </div>
                
                <div class="message-list-container">
                    <div class="message-list" id="message-list">
                        <!-- Messages will be loaded dynamically -->
                    </div>
                </div>
                
                <div class="message-input">
                    <div class="input-group">
                        <button id="attach-file-btn" class="btn btn-outline-secondary">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <textarea id="message-text" placeholder="Type a message..."></textarea>
                        <button id="send-message-btn" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div id="attachment-preview" class="attachment-preview"></div>
                    <input type="file" id="file-input" style="display: none;">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Conversation Modal -->
<div class="modal fade" id="new-conversation-modal" tabindex="-1" aria-labelledby="newConversationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newConversationModalLabel">Start New Conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="new-conversation-form">
                    <div class="mb-3">
                        <label for="conversation-type" class="form-label">Conversation Type</label>
                        <select class="form-select" id="conversation-type" name="conversation_type">
                            <option value="direct">Direct Message</option>
                            <option value="group">Group Chat</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="group-title-container" style="display: none;">
                        <label for="group-title" class="form-label">Group Title</label>
                        <input type="text" class="form-control" id="group-title" name="group_title">
                    </div>
                    
                    <div class="mb-3">
                        <label for="participants" class="form-label">Select Participants</label>
                        <select class="form-select" id="participants" name="participants[]" multiple>
                            <!-- Options will be loaded dynamically -->
                        </select>
                        <div class="form-text">Search and select users to include in the conversation</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="initial-message" class="form-label">Initial Message (Optional)</label>
                        <textarea class="form-control" id="initial-message" name="initial_message" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="related-to-type" class="form-label">Related To (Optional)</label>
                        <select class="form-select" id="related-to-type" name="related_to_type">
                            <option value="">None</option>
                            <option value="application">Visa Application</option>
                            <option value="booking">Booking</option>
                            <option value="task">Task</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="related-id-container" style="display: none;">
                        <label for="related-id" class="form-label">Select Item</label>
                        <select class="form-select" id="related-id" name="related_id">
                            <option value="">-- Select --</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="create-conversation-btn">Create Conversation</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Participant Modal -->
<div class="modal fade" id="add-participant-modal" tabindex="-1" aria-labelledby="addParticipantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addParticipantModalLabel">Add Participants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-participant-form">
                    <input type="hidden" id="add-to-conversation-id" name="conversation_id">
                    <div class="mb-3">
                        <label for="new-participants" class="form-label">Select Users</label>
                        <select class="form-select" id="new-participants" name="new_participants[]" multiple>
                            <!-- Options will be loaded dynamically -->
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="add-participants-btn">Add to Conversation</button>
            </div>
        </div>
    </div>
</div>

<!-- Conversation Info Modal -->
<div class="modal fade" id="conversation-info-modal" tabindex="-1" aria-labelledby="conversationInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="conversationInfoModalLabel">Conversation Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Participants</h6>
                <ul class="participant-list" id="info-participant-list">
                    <!-- Will be filled dynamically -->
                </ul>
                
                <div id="related-info" class="mt-4">
                    <h6>Related To</h6>
                    <p id="related-info-text">Not related to any item</p>
                </div>
                
                <div class="mt-4">
                    <h6>Created</h6>
                    <p id="conversation-created-at"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 