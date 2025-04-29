<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Get and validate input
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid service configuration ID']);
    exit;
}

// Fetch service configuration with related information
$query = "
    SELECT 
        vsc.id,
        vsc.visa_type_id,
        vsc.service_type_id,
        vsc.consultation_mode_id,
        vsc.price,
        vsc.is_active,
        c.id AS country_id
    FROM visa_service_configurations vsc
    JOIN visa_types vt ON vsc.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id
    WHERE vsc.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Service configuration not found']);
    exit;
}

$service = $result->fetch_assoc();
echo json_encode($service);
