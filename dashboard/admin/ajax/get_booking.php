<?php
// Include database connection
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if booking ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

// Get booking ID
$booking_id = intval($_GET['id']);

// Get booking details
$sql = "
    SELECT 
        b.*,
        u.email as user_email,
        vt.name as visa_type_name,
        c.name as country_name,
        st.name as service_type_name,
        cm.name as consultation_mode_name,
        tm.id as team_member_id,
        tm_user.name as team_member_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN visa_types vt ON b.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id 
    JOIN service_types st ON b.service_type_id = st.id
    JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
    LEFT JOIN team_members tm ON b.team_member_id = tm.id
    LEFT JOIN users tm_user ON tm.user_id = tm_user.id
    WHERE b.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $booking = $result->fetch_assoc();
    
    // Get follow-ups, if any
    $follow_ups_sql = "
        SELECT * FROM booking_follow_ups
        WHERE booking_id = ?
        ORDER BY created_at
    ";
    $stmt = $conn->prepare($follow_ups_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $follow_ups_result = $stmt->get_result();
    
    $follow_ups = [];
    if ($follow_ups_result && $follow_ups_result->num_rows > 0) {
        while ($row = $follow_ups_result->fetch_assoc()) {
            $follow_ups[] = $row;
        }
    }
    
    // Add follow-ups to booking data
    $booking['follow_ups'] = $follow_ups;
    
    // Get activity logs
    $logs_sql = "
        SELECT * FROM booking_activity_logs
        WHERE booking_id = ?
        ORDER BY created_at DESC
    ";
    $stmt = $conn->prepare($logs_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $logs_result = $stmt->get_result();
    
    $activity_logs = [];
    if ($logs_result && $logs_result->num_rows > 0) {
        while ($row = $logs_result->fetch_assoc()) {
            $activity_logs[] = $row;
        }
    }
    
    // Add activity logs to booking data
    $booking['activity_logs'] = $activity_logs;
    
    echo json_encode([
        'success' => true,
        'booking' => $booking
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Booking not found'
    ]);
} 