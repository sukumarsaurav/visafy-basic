<?php
$page_title = "Team Management";
$page_specific_css = "assets/css/team.css";
require_once 'includes/header.php';

// Get all team members
$query = "SELECT tm.id, tm.role, tm.custom_role_name, tm.permissions, tm.created_at,
          u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, u.email_verified
          FROM team_members tm
          JOIN users u ON tm.user_id = u.id
          WHERE tm.deleted_at IS NULL
          ORDER BY u.first_name, u.last_name";
$result = $conn->query($query);
$team_members = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $team_members[] = $row;
    }
}

// Generate random invite token
function generateInviteToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Handle invite form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_member'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $custom_role_name = isset($_POST['custom_role_name']) ? trim($_POST['custom_role_name']) : null;
    
    // Validate inputs
    $errors = [];
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if ($role === 'Custom' && empty($custom_role_name)) {
        $errors[] = "Custom role name is required";
    }
    
    // Check if email already exists
    $check_query = "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    
    if (empty($errors)) {
        // Create invite token
        $token = generateInviteToken();
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create user with pending status
            $user_insert = "INSERT INTO users (first_name, last_name, email, password, user_type, email_verified, email_verification_token, email_verification_expires, status) 
                          VALUES (?, ?, ?, '', 'member', 0, ?, ?, 'suspended')";
            $stmt = $conn->prepare($user_insert);
            $stmt->bind_param('sssss', $first_name, $last_name, $email, $token, $expires);
            $stmt->execute();
            
            $user_id = $conn->insert_id;
            
            // Create team member record
            $member_insert = "INSERT INTO team_members (user_id, role, custom_role_name) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($member_insert);
            $stmt->bind_param('iss', $user_id, $role, $custom_role_name);
            $stmt->execute();
            
            // Send invitation email
            $invite_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=" . $token;
            $subject = "Invitation to join the team at Visafy";
            
            $message = "
            <html>
            <head>
                <title>Team Invitation</title>
            </head>
            <body>
                <p>Hello {$first_name} {$last_name},</p>
                <p>You have been invited to join the team at Visafy.</p>
                <p>Please click the link below to activate your account and set your password:</p>
                <p><a href='{$invite_link}'>{$invite_link}</a></p>
                <p>This link will expire in 48 hours.</p>
                <p>If you did not expect this invitation, please ignore this email.</p>
                <p>Regards,<br>The Visafy Team</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Visafy <noreply@visafy.com>' . "\r\n";
            
            mail($email, $subject, $message, $headers);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Invitation sent to {$email}";
            
            // Refresh the page to show updated list
            header("Location: team.php?success=1");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error sending invitation: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle member deletion/deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_member'])) {
    $member_id = $_POST['member_id'];
    $user_id = $_POST['user_id'];
    
    // Soft delete - update status to suspended
    $update_query = "UPDATE users SET status = 'suspended' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Team member deactivated successfully";
        header("Location: team.php?success=2");
        exit;
    } else {
        $error_message = "Error deactivating team member: " . $conn->error;
    }
}

// Handle member reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_member'])) {
    $member_id = $_POST['member_id'];
    $user_id = $_POST['user_id'];
    
    // Update status to active
    $update_query = "UPDATE users SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Team member reactivated successfully";
        header("Location: team.php?success=3");
        exit;
    } else {
        $error_message = "Error reactivating team member: " . $conn->error;
    }
}

// Handle resending invite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_invite'])) {
    $member_id = $_POST['member_id'];
    $user_id = $_POST['user_id'];
    
    // Get user details
    $user_query = "SELECT first_name, last_name, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if ($user) {
        // Create new token
        $token = generateInviteToken();
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Update token in database
        $update_query = "UPDATE users SET email_verification_token = ?, email_verification_expires = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssi', $token, $expires, $user_id);
        
        if ($stmt->execute()) {
            // Send invitation email
            $invite_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=" . $token;
            $subject = "Invitation to join the team at Visafy";
            
            $message = "
            <html>
            <head>
                <title>Team Invitation</title>
            </head>
            <body>
                <p>Hello {$user['first_name']} {$user['last_name']},</p>
                <p>You have been invited to join the team at Visafy.</p>
                <p>Please click the link below to activate your account and set your password:</p>
                <p><a href='{$invite_link}'>{$invite_link}</a></p>
                <p>This link will expire in 48 hours.</p>
                <p>If you did not expect this invitation, please ignore this email.</p>
                <p>Regards,<br>The Visafy Team</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Visafy <noreply@visafy.com>' . "\r\n";
            
            mail($user['email'], $subject, $message, $headers);
            
            $success_message = "Invitation resent to {$user['email']}";
            header("Location: team.php?success=4");
            exit;
        } else {
            $error_message = "Error resending invitation: " . $conn->error;
        }
    } else {
        $error_message = "User not found";
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Invitation sent successfully";
            break;
        case 2:
            $success_message = "Team member deactivated successfully";
            break;
        case 3:
            $success_message = "Team member reactivated successfully";
            break;
        case 4:
            $success_message = "Invitation resent successfully";
            break;
    }
}
?>

<div class="content">
    <h1>Team Management</h1>
    <p>Manage your team members and send invitations to new team members.</p>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Invite New Team Member Section -->
    <div class="section">
        <h2>Invite New Team Member</h2>
        <div class="invite-card">
            <form action="team.php" method="POST" class="invite-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name*</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name*</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="email">Email Address*</label>
                    <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Role*</label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="Case Manager">Case Manager</option>
                            <option value="Document Creator">Document Creator</option>
                            <option value="Career Consultant">Career Consultant</option>
                            <option value="Business Plan Creator">Business Plan Creator</option>
                            <option value="Immigration Assistant">Immigration Assistant</option>
                            <option value="Social Media Manager">Social Media Manager</option>
                            <option value="Leads & CRM Manager">Leads & CRM Manager</option>
                            <option value="Custom">Custom Role</option>
                        </select>
                    </div>
                    <div class="form-group" id="custom_role_group" style="display: none;">
                        <label for="custom_role_name">Custom Role Name*</label>
                        <input type="text" name="custom_role_name" id="custom_role_name" class="form-control">
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="invite_member" class="btn submit-btn">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Team Members Section -->
    <div class="section">
        <h2>Team Members</h2>
        <?php if (empty($team_members)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>No team members yet. Invite someone to get started!</p>
            </div>
        <?php else: ?>
            <div class="members-grid">
                <?php foreach ($team_members as $member): ?>
                    <div class="member-card <?php echo $member['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <div class="member-status">
                            <?php if ($member['status'] === 'active'): ?>
                                <span class="status-indicator active" title="Active"></span>
                            <?php else: ?>
                                <span class="status-indicator inactive" title="Inactive"></span>
                            <?php endif; ?>
                        </div>
                        <div class="member-photo">
                            <?php if (!empty($member['profile_picture']) && file_exists('../../uploads/profiles/' . $member['profile_picture'])): ?>
                                <img src="../../uploads/profiles/<?php echo $member['profile_picture']; ?>" alt="Profile picture">
                            <?php else: ?>
                                <div class="member-initials">
                                    <?php echo substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="member-details">
                            <h3><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h3>
                            <p class="member-role">
                                <?php echo $member['role'] === 'Custom' ? htmlspecialchars($member['custom_role_name']) : htmlspecialchars($member['role']); ?>
                            </p>
                            <p class="member-email"><?php echo htmlspecialchars($member['email']); ?></p>
                            
                            <?php if (!$member['email_verified']): ?>
                                <p class="member-pending">Pending activation</p>
                            <?php endif; ?>
                        </div>
                        <div class="member-actions">
                            <?php if ($member['status'] === 'active'): ?>
                                <form action="team.php" method="POST" class="action-form">
                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                    <button type="submit" name="deactivate_member" class="action-btn danger-btn" onclick="return confirm('Are you sure you want to deactivate this team member?')">
                                        <i class="fas fa-user-slash"></i> Deactivate
                                    </button>
                                </form>
                            <?php else: ?>
                                <form action="team.php" method="POST" class="action-form">
                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                    <button type="submit" name="reactivate_member" class="action-btn success-btn">
                                        <i class="fas fa-user-check"></i> Activate
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if (!$member['email_verified']): ?>
                                <form action="team.php" method="POST" class="action-form">
                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                                    <button type="submit" name="resend_invite" class="action-btn primary-btn">
                                        <i class="fas fa-paper-plane"></i> Resend Invite
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="action-btn edit-btn">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Show/hide custom role name field based on role selection
document.getElementById('role').addEventListener('change', function() {
    const customRoleGroup = document.getElementById('custom_role_group');
    const customRoleInput = document.getElementById('custom_role_name');
    
    if (this.value === 'Custom') {
        customRoleGroup.style.display = 'block';
        customRoleInput.setAttribute('required', 'required');
    } else {
        customRoleGroup.style.display = 'none';
        customRoleInput.removeAttribute('required');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 