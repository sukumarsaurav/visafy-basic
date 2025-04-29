<?php
// Start session if none is active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Log activity function
function logActivity($conn, $user_id, $action, $target_type, $target_id, $details) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, target_type, target_id, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $user_id, $action, $target_type, $target_id, $details);
    $stmt->execute();
}

// Check if required parameters are provided
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    $_SESSION['error_message'] = "Invalid request. Missing parameters.";
    header("Location: clients.php");
    exit();
}

$client_id = $_GET['id'];
$action = $_GET['action'];
$admin_id = $_SESSION['user_id'];

// Validate client exists
$stmt = $conn->prepare("SELECT id, first_name, last_name, status FROM users WHERE id = ? AND user_type = 'applicant'");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Client not found.";
    header("Location: clients.php");
    exit();
}

$client = $result->fetch_assoc();
$client_name = $client['first_name'] . ' ' . $client['last_name'];

// Process status change
if ($action === 'suspend' && $client['status'] === 'active') {
    // Change status to suspended
    $stmt = $conn->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $client_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $details = "Client account suspended: $client_name (ID: $client_id)";
        logActivity($conn, $admin_id, 'suspend_client', 'user', $client_id, $details);
        
        $_SESSION['success_message'] = "Client account has been suspended successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to suspend client account. Please try again.";
    }
    
} elseif ($action === 'activate' && $client['status'] === 'suspended') {
    // Change status to active
    $stmt = $conn->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $client_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $details = "Client account activated: $client_name (ID: $client_id)";
        logActivity($conn, $admin_id, 'activate_client', 'user', $client_id, $details);
        
        $_SESSION['success_message'] = "Client account has been activated successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to activate client account. Please try again.";
    }
    
} else {
    $_SESSION['error_message'] = "Invalid action or client status cannot be changed.";
}

// Redirect back to clients page
header("Location: clients.php");
exit();
?> 