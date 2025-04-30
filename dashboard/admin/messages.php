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
<div class="modal" id="new-conversation-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Start New Conversation</h2>
        <form id="new-conversation-form">
            <div class="form-group">
                <label for="conversation-type">Conversation Type</label>
                <select id="conversation-type" name="conversation_type">
                    <option value="direct">Direct Message</option>
                    <option value="group">Group Chat</option>
                </select>
            </div>
            
            <div class="form-group" id="group-title-container" style="display: none;">
                <label for="group-title">Group Title</label>
                <input type="text" id="group-title" name="group_title" placeholder="Enter group title">
            </div>
            
            <div class="form-group">
                <label for="participants">Select Participants</label>
                <select id="participants" name="participants[]" multiple>
                    <!-- Options will be loaded dynamically -->
                </select>
                <small>Search and select users to include in the conversation</small>
            </div>
            
            <div class="form-group">
                <label for="initial-message">Initial Message (Optional)</label>
                <textarea id="initial-message" name="initial_message" rows="3" placeholder="Type your first message..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="related-to-type">Related To (Optional)</label>
                <select id="related-to-type" name="related_to_type">
                    <option value="">None</option>
                    <option value="application">Visa Application</option>
                    <option value="booking">Booking</option>
                    <option value="task">Task</option>
                    <option value="general">General</option>
                </select>
            </div>
            
            <div class="form-group" id="related-id-container" style="display: none;">
                <label for="related-id">Select Item</label>
                <select id="related-id" name="related_id">
                    <option value="">-- Select --</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Create Conversation</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Participant Modal -->
<div class="modal" id="add-participant-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Add Participants</h2>
        <form id="add-participant-form">
            <input type="hidden" id="add-to-conversation-id" name="conversation_id">
            <div class="form-group">
                <label for="new-participants">Select Users</label>
                <select id="new-participants" name="new_participants[]" multiple>
                    <!-- Options will be loaded dynamically -->
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Add to Conversation</button>
            </div>
        </form>
    </div>
</div>

<!-- Conversation Info Modal -->
<div class="modal" id="conversation-info-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Conversation Information</h2>
        <div class="info-section">
            <h3>Participants</h3>
            <ul class="participant-list" id="info-participant-list">
                <!-- Will be filled dynamically -->
            </ul>
        </div>
        
        <div class="info-section" id="related-info">
            <h3>Related To</h3>
            <p id="related-info-text">Not related to any item</p>
        </div>
        
        <div class="info-section">
            <h3>Created</h3>
            <p id="conversation-created-at"></p>
        </div>
        
        <div class="form-group">
            <button type="button" class="btn close-modal-btn">Close</button>
        </div>
    </div>
</div>

<!-- JavaScript for Modal Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal functionality
    const modals = document.querySelectorAll('.modal');
    const modalTriggers = {
        'new-conversation-btn': 'new-conversation-modal',
        'add-participant-btn': 'add-participant-modal',
        'view-info-btn': 'conversation-info-modal'
    };
    
    // Open modals
    Object.keys(modalTriggers).forEach(triggerId => {
        const trigger = document.getElementById(triggerId);
        const modalId = modalTriggers[triggerId];
        
        if (trigger) {
            trigger.addEventListener('click', function() {
                document.getElementById(modalId).style.display = 'block';
                
                // If this is the new conversation modal or add participant modal, load users
                if (modalId === 'new-conversation-modal' || modalId === 'add-participant-modal') {
                    loadUsers(modalId);
                }
            });
        }
    });
    
    // Load users for participant selectors
    function loadUsers(modalId) {
        const selectElement = modalId === 'new-conversation-modal' ? 
                             document.getElementById('participants') : 
                             document.getElementById('new-participants');
        
        // Clear existing options
        selectElement.innerHTML = '<option value="" disabled>Loading users...</option>';
        
        fetch('ajax/get_users.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear the loading option
                    selectElement.innerHTML = '';
                    
                    // Group users by type
                    const adminUsers = data.users.filter(user => user.user_type === 'admin');
                    const teamUsers = data.users.filter(user => user.user_type === 'member');
                    const clientUsers = data.users.filter(user => user.user_type === 'applicant');
                    
                    // Add optgroups with their respective users
                    if (adminUsers.length > 0) {
                        const adminGroup = document.createElement('optgroup');
                        adminGroup.label = 'Admins';
                        adminUsers.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = `${user.name} (${user.email})`;
                            adminGroup.appendChild(option);
                        });
                        selectElement.appendChild(adminGroup);
                    }
                    
                    if (teamUsers.length > 0) {
                        const teamGroup = document.createElement('optgroup');
                        teamGroup.label = 'Team Members';
                        teamUsers.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = `${user.name} (${user.email})`;
                            teamGroup.appendChild(option);
                        });
                        selectElement.appendChild(teamGroup);
                    }
                    
                    if (clientUsers.length > 0) {
                        const clientGroup = document.createElement('optgroup');
                        clientGroup.label = 'Clients';
                        clientUsers.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = `${user.name} (${user.email})`;
                            clientGroup.appendChild(option);
                        });
                        selectElement.appendChild(clientGroup);
                    }
                    
                    // Initialize select2 for better UX if it's included in the project
                    if (typeof $ !== 'undefined' && $.fn.select2) {
                        $(selectElement).select2({
                            placeholder: 'Select users...',
                            width: '100%'
                        });
                    }
                } else {
                    selectElement.innerHTML = '<option value="" disabled>Failed to load users</option>';
                    console.error('Error loading users:', data.error);
                }
            })
            .catch(error => {
                selectElement.innerHTML = '<option value="" disabled>Failed to load users</option>';
                console.error('Error fetching users:', error);
            });
    }
    
    // Close modals
    const closeButtons = document.querySelectorAll('.modal .close, .close-modal-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Toggle group title field based on conversation type
    const conversationType = document.getElementById('conversation-type');
    const groupTitleContainer = document.getElementById('group-title-container');
    
    if (conversationType && groupTitleContainer) {
        conversationType.addEventListener('change', function() {
            if (this.value === 'group') {
                groupTitleContainer.style.display = 'block';
            } else {
                groupTitleContainer.style.display = 'none';
            }
        });
    }
    
    // Toggle related item selector based on related type
    const relatedToType = document.getElementById('related-to-type');
    const relatedIdContainer = document.getElementById('related-id-container');
    
    if (relatedToType && relatedIdContainer) {
        relatedToType.addEventListener('change', function() {
            if (this.value) {
                relatedIdContainer.style.display = 'block';
                // Here you would load the related items based on the selected type
                loadRelatedItems(this.value);
            } else {
                relatedIdContainer.style.display = 'none';
            }
        });
    }
    
    function loadRelatedItems(type) {
        const relatedIdSelect = document.getElementById('related-id');
        relatedIdSelect.innerHTML = '<option value="">-- Select --</option>';
        
        fetch(`ajax/get_related_items.php?type=${type}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        relatedIdSelect.appendChild(option);
                    });
                } else {
                    console.error('Error loading related items:', data.error);
                }
            })
            .catch(error => {
                console.error('Error loading related items:', error);
            });
    }
    
    // Form submission handlers
    const newConversationForm = document.getElementById('new-conversation-form');
    if (newConversationForm) {
        newConversationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Creating...';
            submitBtn.disabled = true;
            
            // Gather form data
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch('ajax/create_conversation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    // Close modal
                    document.getElementById('new-conversation-modal').style.display = 'none';
                    
                    // Reset form
                    newConversationForm.reset();
                    
                    // Redirect to the conversation
                    window.location.href = `messages.php?conversation_id=${data.conversation_id}`;
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                // Reset button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                console.error('Error creating conversation:', error);
                alert('An error occurred while creating the conversation. Please try again.');
            });
        });
    }
    
    // Add participant form submission
    const addParticipantForm = document.getElementById('add-participant-form');
    if (addParticipantForm) {
        addParticipantForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Adding...';
            submitBtn.disabled = true;
            
            // Gather form data
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch('ajax/add_conversation_participants.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    // Close modal
                    document.getElementById('add-participant-modal').style.display = 'none';
                    
                    // Reset form
                    addParticipantForm.reset();
                    
                    // Refresh participant list or entire conversation view
                    // You might want to implement this part based on your app's structure
                    alert('Participants added successfully!');
                    location.reload(); // Simple refresh for now
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                // Reset button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                console.error('Error adding participants:', error);
                alert('An error occurred while adding participants. Please try again.');
            });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>