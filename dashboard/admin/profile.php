<?php
$page_title = "My Profile";
$page_specific_js = "assets/js/profile.js";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Load user data
$user_id = $_SESSION["id"];
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, user_type, email_verified, profile_picture, 
                       created_at, updated_at, status, auth_provider FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Format profile picture URL
$profile_img = '../assets/images/default-profile.jpg'; // Default image
if (!empty($user['profile_picture'])) {
    $profile_path = '../../uploads/profiles/' . $user['profile_picture'];
    if (file_exists($profile_path)) {
        $profile_img = $profile_path;
    }
}
?>

<div class="content">
    <div class="profile-container">
        <div class="profile-grid">
            <div class="profile-sidebar">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="profile-image-container">
                            <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-image" id="profile-image">
                            <div class="image-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Change Photo</span>
                            </div>
                        </div>
                        
                        <h3 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <span class="user-type-badge"><?php echo htmlspecialchars($user['user_type']); ?></span>
                        
                        <div class="profile-info">
                            <p><i class="fas fa-envelope info-icon"></i><?php echo htmlspecialchars($user['email']); ?></p>
                            <p><i class="fas fa-calendar-alt info-icon"></i>Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                        </div>
                        
                        <div class="upload-section">
                            <button id="upload-photo-btn" class="button button-outline">
                                <i class="fas fa-upload icon-left"></i>Upload Photo
                            </button>
                            <input type="file" id="photo-upload" accept="image/*" class="hidden-input">
                        </div>
                    </div>
                </div>
                
                <div class="card account-info-card">
                    <div class="card-header">
                        <h5>Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="status-badge <?php echo $user['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Email Verification</div>
                            <div class="info-value">
                                <span class="status-badge <?php echo $user['email_verified'] ? 'status-verified' : 'status-unverified'; ?>">
                                    <?php echo $user['email_verified'] ? 'Verified' : 'Unverified'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Authentication Method</div>
                            <div class="info-value">
                                <?php if ($user['auth_provider'] == 'google'): ?>
                                    <i class="fab fa-google icon-left"></i> Google
                                <?php else: ?>
                                    <i class="fas fa-key icon-left"></i> Local
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value">
                                <?php echo date('M d, Y', strtotime($user['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="profile-main">
                <div class="card">
                    <div class="card-header">
                        <h5>Personal Information</h5>
                        <button id="edit-profile-btn" class="button button-small">
                            <i class="fas fa-edit icon-left"></i> Edit Profile
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="profile-form">
                            <div class="form-row">
                                <div class="form-column">
                                    <label for="first-name" class="form-label">First Name</label>
                                    <input type="text" class="form-input" id="first-name" name="first_name" 
                                        value="<?php echo htmlspecialchars($user['first_name']); ?>" readonly>
                                </div>
                                <div class="form-column">
                                    <label for="last-name" class="form-label">Last Name</label>
                                    <input type="text" class="form-input" id="last-name" name="last_name" 
                                        value="<?php echo htmlspecialchars($user['last_name']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-input" id="email" name="email" 
                                    value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <div class="form-help">
                                    <?php if (!$user['email_verified']): ?>
                                        <span class="text-warning">Your email is not verified. 
                                            <a href="#" id="resend-verification">Resend verification email</a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" id="save-profile-btn" class="button button-success hidden">
                                    <i class="fas fa-save icon-left"></i> Save Changes
                                </button>
                                <button type="button" id="cancel-edit-btn" class="button button-outline hidden">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card security-card">
                    <div class="card-header">
                        <h5>Security Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="password-form">
                            <div class="form-group">
                                <label for="current-password" class="form-label">Current Password</label>
                                <input type="password" class="form-input" id="current-password" name="current_password">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-column">
                                    <label for="new-password" class="form-label">New Password</label>
                                    <input type="password" class="form-input" id="new-password" name="new_password">
                                </div>
                                <div class="form-column">
                                    <label for="confirm-password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-input" id="confirm-password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="password-strength" id="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-indicator" id="strength-indicator"></div>
                                </div>
                                <div class="strength-text" id="strength-text">Password strength</div>
                            </div>
                            
                            <button type="submit" class="button">
                                <i class="fas fa-lock icon-left"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card notifications-card">
                    <div class="card-header">
                        <h5>Notification Preferences</h5>
                    </div>
                    <div class="card-body">
                        <form id="notification-form">
                            <?php
                            // Fetch notification preferences
                            $stmt = $conn->prepare("SELECT notification_type, email_enabled, push_enabled, in_app_enabled 
                                               FROM notification_preferences WHERE user_id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $notifications = [];
                            
                            while ($row = $result->fetch_assoc()) {
                                $notifications[$row['notification_type']] = $row;
                            }
                            
                            // Define notification types and friendly names
                            $notification_types = [
                                'application_status_change' => 'Application Status Updates',
                                'document_requested' => 'Document Requests',
                                'booking_created' => 'New Bookings',
                                'booking_rescheduled' => 'Booking Reschedules',
                                'task_assigned' => 'Task Assignments',
                                'message_received' => 'New Messages',
                                'system_alert' => 'System Alerts'
                            ];
                            
                            foreach ($notification_types as $type => $name):
                                $prefs = isset($notifications[$type]) ? $notifications[$type] : 
                                    ['email_enabled' => 1, 'push_enabled' => 1, 'in_app_enabled' => 1];
                            ?>
                            <div class="notification-preference">
                                <div class="preference-name"><?php echo $name; ?></div>
                                <div class="preference-options">
                                    <div class="toggle-option">
                                        <input class="toggle-checkbox" type="checkbox" id="email-<?php echo $type; ?>" 
                                            name="notifications[<?php echo $type; ?>][email]" <?php echo $prefs['email_enabled'] ? 'checked' : ''; ?>>
                                        <label class="toggle-label" for="email-<?php echo $type; ?>">Email</label>
                                    </div>
                                    <div class="toggle-option">
                                        <input class="toggle-checkbox" type="checkbox" id="push-<?php echo $type; ?>" 
                                            name="notifications[<?php echo $type; ?>][push]" <?php echo $prefs['push_enabled'] ? 'checked' : ''; ?>>
                                        <label class="toggle-label" for="push-<?php echo $type; ?>">Push</label>
                                    </div>
                                    <div class="toggle-option">
                                        <input class="toggle-checkbox" type="checkbox" id="inapp-<?php echo $type; ?>" 
                                            name="notifications[<?php echo $type; ?>][inapp]" <?php echo $prefs['in_app_enabled'] ? 'checked' : ''; ?>>
                                        <label class="toggle-label" for="inapp-<?php echo $type; ?>">In-App</label>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <button type="submit" class="button">
                                <i class="fas fa-save icon-left"></i> Save Preferences
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Crop Image Modal -->
<div class="modal" id="crop-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Crop Profile Image</h5>
            <button type="button" class="close-button">&times;</button>
        </div>
        <div class="modal-body">
            <div class="img-container">
                <img id="crop-image" src="" alt="Image to crop">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button-outline modal-cancel-btn">Cancel</button>
            <button type="button" class="button" id="crop-btn">Crop & Save</button>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
