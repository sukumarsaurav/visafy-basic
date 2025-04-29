<?php
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Fetch all service configurations with related information
$query = "
    SELECT 
        vsc.id,
        c.name AS country_name,
        vt.name AS visa_type_name,
        st.name AS service_type_name,
        cm.name AS consultation_mode_name,
        vsc.price,
        vsc.is_active,
        c.id AS country_id,
        vt.id AS visa_type_id,
        st.id AS service_type_id,
        cm.id AS consultation_mode_id
    FROM visa_service_configurations vsc
    JOIN visa_types vt ON vsc.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id
    JOIN service_types st ON vsc.service_type_id = st.id
    JOIN consultation_modes cm ON vsc.consultation_mode_id = cm.id
    ORDER BY c.name, vt.name, st.name, cm.name
";

$result = $conn->query($query);

if ($result) {
    $services = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($services);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch service configurations'
    ]);
}
