<?php
// Include necessary files
require_once '../../config/db_connect.php';
require_once '../../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an applicant
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'applicant') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION["id"];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'applicant'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: ../../login.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Get dashboard statistics
$total_applications = 0;
$active_applications = 0;
$pending_documents = 0;
$completed_applications = 0;

// Get total applications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE user_id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_applications = $result->fetch_assoc()['count'];
$stmt->close();

// Get active applications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE user_id = ? AND status IN ('submitted', 'in_review', 'document_requested', 'processing') AND deleted_at IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$active_applications = $result->fetch_assoc()['count'];
$stmt->close();

// Get pending documents
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM application_document_requests adr
    JOIN visa_applications va ON adr.application_id = va.id
    WHERE va.user_id = ? 
    AND adr.status = 'requested'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$pending_documents = $result->fetch_assoc()['count'];
$stmt->close();

// Get completed applications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE user_id = ? AND status IN ('approved', 'rejected') AND deleted_at IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$completed_applications = $result->fetch_assoc()['count'];
$stmt->close();

// Get recent applications
$recent_applications = [];
$stmt = $conn->prepare("
    SELECT va.*, vt.name as visa_type_name 
    FROM visa_applications va
    JOIN visa_types vt ON va.visa_type_id = vt.id
    WHERE va.user_id = ? AND va.deleted_at IS NULL
    ORDER BY va.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_applications[] = $row;
}
$stmt->close();

// Get document requests
$document_requests = [];
$stmt = $conn->prepare("
    SELECT adr.*, dt.name as document_type_name, va.reference_number as application_reference
    FROM application_document_requests adr
    JOIN document_types dt ON adr.document_type_id = dt.id
    JOIN visa_applications va ON adr.application_id = va.id
    WHERE va.user_id = ? AND adr.status = 'requested'
    ORDER BY adr.requested_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $document_requests[] = $row;
}
$stmt->close();

// Check for unread notifications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_result = $stmt->get_result();
$notification_count = $notif_result->fetch_assoc()['count'];
$stmt->close();

// Get recent notifications (limit to 5)
$stmt = $conn->prepare("SELECT id, title, content, is_read, created_at FROM notifications 
                       WHERE user_id = ? AND is_read = 0 
                       ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$notifications_list = [];
while ($notification = $notifications->fetch_assoc()) {
    $notifications_list[] = $notification;
}
$stmt->close();

// Page title
$page_title = "Applicant Dashboard";

// Include header
include 'includes/header.php';
?>

<!-- Dashboard Content -->
<div class="dashboard-content">




<?php
// Include footer
include 'includes/footer.php';
?>