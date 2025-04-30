<?php
$page_title = "My Profile";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['profile_picture']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($filetype, $allowed)) {
            $errors[] = "Only JPG, JPEG & PNG files are allowed";
        } else {
            $new_filename = uniqid('profile_') . '.' . $filetype;
            $upload_path = '../../uploads/profile/' . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if (!empty($user['profile_picture'])) {
                    $old_file = '../../uploads/profile/' . $user['profile_picture'];
                    if (file_exists($old_file)) unlink($old_file);
                }
                
                $profile_picture = $new_filename;
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        }
    }
    
    if (empty($errors)) {
        // Update profile in database
        $update_query = "UPDATE users SET first_name = ?, last_name = ?";
        $params = [$first_name, $last_name];
        $types = "ss";
        
        if (isset($profile_picture)) {
            $update_query .= ", profile_picture = ?";
            $params[] = $profile_picture;
            $types .= "s";
        }
        
        // Only update email if changed
        if ($email !== $user['email']) {
            $update_query .= ", email = ?, email_verified = 0";
            $params[] = $email;
            $types .= "s";
        }
        
        $update_query .= " WHERE id = ?";
        $params[] = $user_id;
        $types .= "i";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Profile updated successfully!";
            if ($email !== $user['email']) {
                $_SESSION['info_msg'] = "Please verify your new email address.";
                // TODO: Send verification email
            }
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Failed to update profile";
        }
        $stmt->close();
    }
}

$success_message = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : '';
$error_message = !empty($errors) ? implode('<br>', $errors) : '';

// Clear session messages
if (isset($_SESSION['success_msg'])) unset($_SESSION['success_msg']);

// Get user's applications count
$app_stmt = $conn->prepare("
    SELECT COUNT(*) as total_applications,
           SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications
    FROM visa_applications 
    WHERE user_id = ? AND deleted_at IS NULL
");
$app_stmt->bind_param("i", $user_id);
$app_stmt->execute();
$applications = $app_stmt->get_result()->fetch_assoc();
$app_stmt->close();

// Get user's recent activities
$activity_stmt = $conn->prepare("
    SELECT 'application' as type, created_at, status as details
    FROM visa_applications 
    WHERE user_id = ? AND deleted_at IS NULL
    UNION ALL
    SELECT 'document' as type, ad.uploaded_at as created_at, 
           CONCAT(dt.name, ' uploaded') as details
    FROM application_documents ad
    JOIN application_document_requests adr ON ad.request_id = adr.id
    JOIN document_types dt ON adr.document_type_id = dt.id
    JOIN visa_applications va ON adr.application_id = va.id
    WHERE va.user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$activity_stmt->bind_param("ii", $user_id, $user_id);
$activity_stmt->execute();
$activities = $activity_stmt->get_result();
$activity_stmt->close();
?>

<div class="profile-container">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="profile-header">
        <div class="profile-image-container">
            <?php if (!empty($user['profile_picture'])): ?>
                <img src="../../uploads/profile/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Image" class="profile-image" id="profile-image">
            <?php else: ?>
                <div class="profile-image-placeholder">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="image-overlay" id="image-overlay">
                <i class="fas fa-camera"></i>
                <span>Change Photo</span>
            </div>
        </div>
        <div class="profile-info">
            <h1 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
            <p class="profile-entity-type">admin</p>
            <div class="verification-status <?php echo $user['email_verified'] ? 'verified' : 'unverified'; ?>">
                <?php echo $user['email_verified'] ? 'Verified' : 'Unverified'; ?>
            </div>
        </div>
    </div>
    
    <div class="profile-tabs">
        <button class="tab-btn active" data-tab="general">General Information</button>
        <button class="tab-btn" data-tab="professional">Professional Details</button>
        <button class="tab-btn" data-tab="security">Security</button>
    </div>
    
    <form method="POST" action="" enctype="multipart/form-data" class="profile-form" id="profile-form">
        <input type="file" id="photo-upload" name="profile_picture" accept="image/*" class="hidden-input">
        
        <div class="tab-content active" id="general-tab">
            <div class="form-group-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
            </div>
            
            <div class="form-group-row">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small>Email cannot be changed. Contact support for assistance.</small>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="+919991289245">
                </div>
            </div>
            
            <div class="form-group-row">
                <div class="form-group">
                    <label for="joined">Joined Date</label>
                    <input type="text" id="joined" value="April 2025" disabled>
                </div>
                <div class="form-group">
                    <label for="last_updated">Last Updated</label>
                    <input type="text" id="last_updated" value="Apr 29, 2025" disabled>
                </div>
            </div>
        </div>
        
        <div class="tab-content" id="professional-tab">
            <!-- Professional details tab content -->
            <div class="form-group">
                <label for="bio">Bio / Description</label>
                <textarea id="bio" name="bio" rows="5">Professional details go here.</textarea>
            </div>
        </div>
        
        <div class="tab-content" id="security-tab">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password">
            </div>
            
            <div class="form-group-row">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8">
                </div>
            </div>
            
            <div class="password-strength">Password strength</div>
            
            <div class="security-note">
                <div class="icon"><i class="fas fa-shield-alt"></i></div>
                <div class="content">
                    <h3>Password Security Tips</h3>
                    <ul>
                        <li>Use at least 8 characters, including uppercase, lowercase, numbers, and special characters</li>
                        <li>Don't reuse passwords from other websites</li>
                        <li>Update your password regularly</li>
                        <li>Never share your password with others</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="save-btn">Save Changes</button>
            <button type="button" class="cancel-btn" id="cancel-btn">Cancel</button>
        </div>
    </form>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current button
            this.classList.add('active');
            
            // Show corresponding tab content
            const tabId = this.getAttribute('data-tab') + '-tab';
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Profile image change functionality
    const imageOverlay = document.getElementById('image-overlay');
    const photoUpload = document.getElementById('photo-upload');
    const profileImage = document.getElementById('profile-image');
    const cropModal = document.getElementById('crop-modal');
    const cropImage = document.getElementById('crop-image');
    const cropBtn = document.getElementById('crop-btn');
    const closeButton = document.querySelector('.close-button');
    const modalCancelBtn = document.querySelector('.modal-cancel-btn');
    
    if (imageOverlay && photoUpload) {
        imageOverlay.addEventListener('click', function() {
            photoUpload.click();
        });
        
        photoUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // In a real implementation, this would show the crop modal
                    if (cropModal && cropImage) {
                        cropImage.src = e.target.result;
                        cropModal.style.display = 'block';
                    } else {
                        // Fallback if crop modal not available
                        if (profileImage) {
                            profileImage.src = e.target.result;
                        }
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Crop modal functionality
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            cropModal.style.display = 'none';
        });
    }
    
    if (modalCancelBtn) {
        modalCancelBtn.addEventListener('click', function() {
            cropModal.style.display = 'none';
        });
    }
    
    if (cropBtn) {
        cropBtn.addEventListener('click', function() {
            // In a real implementation, this would handle cropping
            cropModal.style.display = 'none';
            // Update profile image with cropped version
            if (profileImage && cropImage) {
                profileImage.src = cropImage.src;
            }
        });
    }
    
    // Form cancel button
    const cancelBtn = document.getElementById('cancel-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.reload();
        });
    }
    
    // Password strength meter functionality
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('strength-indicator');
    const strengthText = document.getElementById('strength-text');
    
    if (passwordInput && strengthIndicator && strengthText) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;
            if (password.match(/[^A-Za-z0-9]/)) strength += 25;
            
            strengthIndicator.style.width = strength + '%';
            
            if (strength < 25) {
                strengthIndicator.style.backgroundColor = '#ff4d4d'; // Red
                strengthText.textContent = 'Very Weak';
            } else if (strength < 50) {
                strengthIndicator.style.backgroundColor = '#ffa64d'; // Orange
                strengthText.textContent = 'Weak';
            } else if (strength < 75) {
                strengthIndicator.style.backgroundColor = '#ffff4d'; // Yellow
                strengthText.textContent = 'Medium';
            } else if (strength < 100) {
                strengthIndicator.style.backgroundColor = '#4dff4d'; // Light green
                strengthText.textContent = 'Strong';
            } else {
                strengthIndicator.style.backgroundColor = '#1a8cff'; // Blue
                strengthText.textContent = 'Very Strong';
            }
        });
    }
    
    // Password confirmation validation
    const confirmPassword = document.getElementById('confirm_password');
    
    if (passwordInput && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

