<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate input
$id = isset($_POST['id']) ? intval($_POST['id']) : null;
$visa_type_id = isset($_POST['visa_type_id']) ? intval($_POST['visa_type_id']) : null;
$service_type_id = isset($_POST['service_type_id']) ? intval($_POST['service_type_id']) : null;
$consultation_mode_id = isset($_POST['consultation_mode_id']) ? intval($_POST['consultation_mode_id']) : null;
$price = isset($_POST['price']) ? floatval($_POST['price']) : null;
$is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

// Validate required fields
if (!$visa_type_id || !$service_type_id || !$consultation_mode_id || $price === null) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Check for duplicate configuration
$check_query = "
    SELECT id FROM visa_service_configurations 
    WHERE visa_type_id = ? AND service_type_id = ? AND consultation_mode_id = ?
    AND id != IFNULL(?, 0)
";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("iiii", $visa_type_id, $service_type_id, $consultation_mode_id, $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'A configuration with these settings already exists'
    ]);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    if ($id) {
        // Update existing configuration
        $query = "
            UPDATE visa_service_configurations 
            SET visa_type_id = ?, 
                service_type_id = ?, 
                consultation_mode_id = ?, 
                price = ?, 
                is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiidii", 
            $visa_type_id, 
            $service_type_id, 
            $consultation_mode_id, 
            $price, 
            $is_active,
            $id
        );
    } else {
        // Insert new configuration
        $query = "
            INSERT INTO visa_service_configurations 
            (visa_type_id, service_type_id, consultation_mode_id, price, is_active) 
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiidi", 
            $visa_type_id, 
            $service_type_id, 
            $consultation_mode_id, 
            $price, 
            $is_active
        );
    }

    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Service configuration saved successfully',
            'id' => $id ?: $conn->insert_id
        ]);
    } else {
        throw new Exception('Failed to save service configuration');
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
