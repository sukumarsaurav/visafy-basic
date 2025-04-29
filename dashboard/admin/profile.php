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
        <div class="row">
            <div class="col-lg-4">
                <div class="card profile-card">
                    <div class="card-body text-center">
                        <div class="profile-image-container">
                            <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-image" id="profile-image">
                            <div class="image-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Change Photo</span>
                            </div>
                        </div>
                        
                        <h3 class="mt-3"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <span class="badge bg-primary text-capitalize"><?php echo htmlspecialchars($user['user_type']); ?></span>
                        
                        <div class="mt-3">
                            <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                            <p><i class="fas fa-calendar-alt me-2"></i>Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                        </div>
                        
                        <div class="mt-4">
                            <button id="upload-photo-btn" class="btn btn-outline-primary">
                                <i class="fas fa-upload me-2"></i>Upload Photo
                            </button>
                            <input type="file" id="photo-upload" accept="image/*" style="display: none;">
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="badge <?php echo $user['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Email Verification</div>
                            <div class="info-value">
                                <span class="badge <?php echo $user['email_verified'] ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo $user['email_verified'] ? 'Verified' : 'Unverified'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Authentication Method</div>
                            <div class="info-value">
                                <?php if ($user['auth_provider'] == 'google'): ?>
                                    <i class="fab fa-google me-1"></i> Google
                                <?php else: ?>
                                    <i class="fas fa-key me-1"></i> Local
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
            
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Personal Information</h5>
                        <button id="edit-profile-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit me-1"></i> Edit Profile
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="profile-form">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first-name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first-name" name="first_name" 
                                        value="<?php echo htmlspecialchars($user['first_name']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="last-name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last-name" name="last_name" 
                                        value="<?php echo htmlspecialchars($user['last_name']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                    value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <div class="form-text">
                                    <?php if (!$user['email_verified']): ?>
                                        <span class="text-warning">Your email is not verified. 
                                            <a href="#" id="resend-verification">Resend verification email</a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col">
                                    <button type="submit" id="save-profile-btn" class="btn btn-success" style="display: none;">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                    <button type="button" id="cancel-edit-btn" class="btn btn-outline-secondary ms-2" style="display: none;">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Security Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="password-form">
                            <div class="mb-3">
                                <label for="current-password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current-password" name="current_password">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="new-password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new-password" name="new_password">
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm-password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm-password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="password-strength" id="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-indicator" id="strength-indicator"></div>
                                </div>
                                <div class="strength-text" id="strength-text">Password strength</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-lock me-1"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
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
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email-<?php echo $type; ?>" 
                                            name="notifications[<?php echo $type; ?>][email]" <?php echo $prefs['email_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email-<?php echo $type; ?>">Email</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="push-<?php echo $type; ?>" 
                                            name="notifications[<?php echo $type; ?>][push]" <?php echo $prefs['push_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="push-<?php echo $type; ?>">Push</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="inapp-<?php echo $type; ?>" 
                                            name="notifications[<?php echo $type; ?>][inapp]" <?php echo $prefs['in_app_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="inapp-<?php echo $type; ?>">In-App</label>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-save me-1"></i> Save Preferences
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Crop Image Modal -->
<div class="modal fade" id="crop-modal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cropModalLabel">Crop Profile Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="img-container">
                    <img id="crop-image" src="" alt="Image to crop">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="crop-btn">Crop & Save</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
