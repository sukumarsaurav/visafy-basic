<?php
// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is an applicant
if (!isLoggedIn() || $_SESSION['user_role'] !== 'applicant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$application_id = isset($data['id']) ? (int)$data['id'] : 0;

// Validate application ID
if ($application_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit();
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Check if application belongs to the user and is in draft status
$stmt = $conn->prepare("SELECT id FROM applications WHERE id = ? AND user_id = ? AND status = 'draft'");
$stmt->bind_param("ii", $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Application not found or cannot be deleted']);
    exit();
}

// Begin transaction
$conn->begin_transaction();

try {
    // Delete application documents
    $stmt = $conn->prepare("DELETE FROM application_documents WHERE application_id = ?");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    
    // Delete application
    $stmt = $conn->prepare("DELETE FROM applications WHERE id = ? AND user_id = ? AND status = 'draft'");
    $stmt->bind_param("ii", $application_id, $user_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Application deleted successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting application: ' . $e->getMessage()]);
}
?> 