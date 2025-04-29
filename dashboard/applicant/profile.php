<?php
$page_title = "My Profile";
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
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-cover"></div>
        <div class="profile-info">
            <div class="profile-avatar">
                <img src="<?php echo $profile_img; ?>" alt="Profile Picture">
                <button class="change-avatar-btn" id="changeAvatarBtn">
                    <i class="fas fa-camera"></i>
                </button>
            </div>
            <div class="profile-details">
                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="profile-badges">
                    <span class="badge">Applicant</span>
                    <?php if ($user['email_verified']): ?>
                        <span class="badge verified"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Content -->
    <div class="profile-content">
        <div class="profile-grid">
            <!-- Profile Update Form -->
            <div class="profile-section">
                <div class="section-header">
                    <h2>Personal Information</h2>
                    <button class="btn btn-secondary" id="editProfileBtn">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_msg'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo htmlspecialchars($_SESSION['success_msg']);
                        unset($_SESSION['success_msg']);
                        ?>
                    </div>
                <?php endif; ?>

                <form id="profileForm" method="POST" enctype="multipart/form-data" class="profile-form">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="profile_picture">Profile Picture</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="hidden">
                        <div class="file-upload-wrapper">
                            <button type="button" class="btn btn-outline" id="uploadTrigger">
                                <i class="fas fa-upload"></i> Choose Image
                            </button>
                            <span id="fileName">No file chosen</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Statistics Section -->
            <div class="profile-section">
                <h2>Overview</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $applications['total_applications']; ?></h3>
                            <p>Total Applications</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $applications['approved_applications']; ?></h3>
                            <p>Approved Applications</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="profile-section">
                <h2>Recent Activity</h2>
                <div class="activity-timeline">
                    <?php if ($activities->num_rows > 0): ?>
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas <?php echo $activity['type'] === 'application' ? 'fa-file-alt' : 'fa-upload'; ?>"></i>
                                </div>
                                <div class="activity-details">
                                    <p><?php echo htmlspecialchars($activity['details']); ?></p>
                                    <span class="activity-time">
                                        <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-activity">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const editBtn = document.getElementById('editProfileBtn');
    const cancelBtn = document.getElementById('cancelEdit');
    const uploadTrigger = document.getElementById('uploadTrigger');
    const fileInput = document.getElementById('profile_picture');
    const fileName = document.getElementById('fileName');
    
    // Enable/disable form fields
    function toggleForm(enabled) {
        const inputs = form.querySelectorAll('input');
        inputs.forEach(input => input.disabled = !enabled);
        form.classList.toggle('editing', enabled);
    }
    
    // Initially disable form
    toggleForm(false);
    
    editBtn.addEventListener('click', () => toggleForm(true));
    cancelBtn.addEventListener('click', () => {
        toggleForm(false);
        form.reset();
    });
    
    // Handle file upload
    uploadTrigger.addEventListener('click', () => fileInput.click());
    
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            fileName.textContent = this.files[0].name;
        } else {
            fileName.textContent = 'No file chosen';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
