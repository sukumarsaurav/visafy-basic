<?php
// Set page variables
$page_title = "Booking Management";
$page_specific_css = "assets/css/bookings.css";
$page_header = "Booking Management";

// Include header (handles session and authentication)
require_once 'includes/header.php';

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo "<div class='error-message'>Access denied. Only admin users can access this page.</div>";
    require_once 'includes/footer.php';
    exit;
}

// Make sure user_id is set
if (!isset($_SESSION['id'])) {
    echo "<div class='error-message'>Session error. Please log in again.</div>";
    require_once 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['id'];
$user_type = $_SESSION['user_type'];

// Get team members if admin
$team_members = [];
$max_concurrent_bookings = 1;

if ($user_type === 'admin') {
    // Get team members for this admin
    $team_sql = "SELECT id, user_id, role, custom_role_name FROM team_members WHERE deleted_at IS NULL";
    $stmt = $conn->prepare($team_sql);
    $stmt->execute();
    $team_members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Create admin_booking_settings table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `admin_booking_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `max_concurrent_bookings` int(11) NOT NULL DEFAULT 1,
        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $conn->query($create_table_sql);
    
    // Get booking capacity setting
    $capacity_sql = "SELECT COALESCE(max_concurrent_bookings, 1) as max_bookings FROM admin_booking_settings WHERE user_id = ?";
    $stmt = $conn->prepare($capacity_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $capacity_result = $stmt->get_result();
    if ($capacity_result->num_rows > 0) {
        $capacity_data = $capacity_result->fetch_assoc();
        $max_concurrent_bookings = $capacity_data['max_bookings'];
    }
}

// Handle form submissions (availability, capacity, timeslot settings)
$success_message = "";
$error_message = "";

// Rest of the code remains the same...

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process different form actions
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_capacity':
                if ($user_type === 'admin' && isset($_POST['max_concurrent_bookings']) && is_numeric($_POST['max_concurrent_bookings'])) {
                    $max_bookings = max(1, intval($_POST['max_concurrent_bookings']));
                    
                    // Check if capacity setting exists
                    $check_stmt = $conn->prepare("SELECT id FROM admin_booking_settings WHERE user_id = ?");
                    $check_stmt->bind_param("i", $user_id);
                    $check_stmt->execute();
                    
                    if ($check_stmt->get_result()->num_rows > 0) {
                        // Update existing
                        $update_stmt = $conn->prepare("UPDATE admin_booking_settings SET max_concurrent_bookings = ? WHERE user_id = ?");
                        $update_stmt->bind_param("ii", $max_bookings, $user_id);
                        if ($update_stmt->execute()) {
                            $success_message = "Booking capacity updated successfully!";
                            $max_concurrent_bookings = $max_bookings;
                        } else {
                            $error_message = "Failed to update booking capacity: " . $conn->error;
                        }
                    } else {
                        // Insert new
                        $insert_stmt = $conn->prepare("INSERT INTO admin_booking_settings (user_id, max_concurrent_bookings) VALUES (?, ?)");
                        $insert_stmt->bind_param("ii", $user_id, $max_bookings);
                        if ($insert_stmt->execute()) {
                            $success_message = "Booking capacity set successfully!";
                            $max_concurrent_bookings = $max_bookings;
                        } else {
                            $error_message = "Failed to set booking capacity: " . $conn->error;
                        }
                    }
                }
                break;
                
            case 'add_timeslot':
                if (isset($_POST['day_of_week'], $_POST['start_time'], $_POST['end_time'])) {
                    $day_of_week = intval($_POST['day_of_week']);
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $is_available = isset($_POST['is_available']) ? 1 : 0;
                    $slot_duration = isset($_POST['slot_duration']) ? intval($_POST['slot_duration']) : 60;
                    $buffer_time = isset($_POST['buffer_time']) ? intval($_POST['buffer_time']) : 0;
                    
                    // Validate time format and order
                    if ($start_time >= $end_time) {
                        $error_message = "End time must be after start time.";
                        break;
                    }
                    
                    // Check for overlapping timeslots
                    $check_stmt = $conn->prepare("SELECT id FROM timeslot_configurations 
                                                 WHERE user_id = ? AND day_of_week = ? AND 
                                                 ((start_time <= ? AND end_time > ?) OR 
                                                 (start_time < ? AND end_time >= ?) OR 
                                                 (start_time >= ? AND end_time <= ?))");
                    $check_stmt->bind_param("iissssss", $user_id, $day_of_week, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) {
                        $error_message = "This timeslot overlaps with an existing timeslot for this day.";
                        break;
                    }
                    
                    // Insert new timeslot
                    $stmt = $conn->prepare("INSERT INTO timeslot_configurations 
                                          (user_id, day_of_week, start_time, end_time, is_available, slot_duration, buffer_time) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissiii", $user_id, $day_of_week, $start_time, $end_time, $is_available, $slot_duration, $buffer_time);
                    
                    if ($stmt->execute()) {
                        $success_message = "Timeslot added successfully!";
                    } else {
                        $error_message = "Failed to add timeslot: " . $conn->error;
                    }
                }
                break;
                
            case 'delete_timeslot':
                if (isset($_POST['timeslot_id']) && is_numeric($_POST['timeslot_id'])) {
                    $timeslot_id = intval($_POST['timeslot_id']);
                    
                    // Verify timeslot belongs to this user
                    $check_stmt = $conn->prepare("SELECT id FROM timeslot_configurations WHERE id = ? AND user_id = ?");
                    $check_stmt->bind_param("ii", $timeslot_id, $user_id);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows === 0) {
                        $error_message = "Timeslot not found or you don't have permission to delete it.";
                        break;
                    }
                    
                    // Delete the timeslot
                    $stmt = $conn->prepare("DELETE FROM timeslot_configurations WHERE id = ?");
                    $stmt->bind_param("i", $timeslot_id);
                    if ($stmt->execute()) {
                        $success_message = "Timeslot deleted successfully!";
                    } else {
                        $error_message = "Failed to delete timeslot: " . $conn->error;
                    }
                }
                break;
                
            case 'add_date_override':
                if (isset($_POST['override_date'], $_POST['is_date_available'])) {
                    $override_date = $_POST['override_date'];
                    $is_available = intval($_POST['is_date_available']);
                    $reason = trim($_POST['reason'] ?? '');
                    
                    // Validate date format
                    $date_obj = DateTime::createFromFormat('Y-m-d', $override_date);
                    if (!$date_obj || $date_obj->format('Y-m-d') !== $override_date) {
                        $error_message = "Invalid date format.";
                        break;
                    }
                    
                    // Check if override exists
                    $check_stmt = $conn->prepare("SELECT id FROM availability_overrides WHERE user_id = ? AND date = ?");
                    $check_stmt->bind_param("is", $user_id, $override_date);
                    $check_stmt->execute();
                    
                    if ($check_stmt->get_result()->num_rows > 0) {
                        // Update existing
                        $update_stmt = $conn->prepare("UPDATE availability_overrides SET is_available = ?, reason = ? WHERE user_id = ? AND date = ?");
                        $update_stmt->bind_param("issi", $is_available, $reason, $user_id, $override_date);
                        if ($update_stmt->execute()) {
                            $success_message = "Date availability updated successfully!";
                        } else {
                            $error_message = "Failed to update date availability: " . $conn->error;
                        }
                    } else {
                        // Insert new
                        $insert_stmt = $conn->prepare("INSERT INTO availability_overrides (user_id, date, is_available, reason) VALUES (?, ?, ?, ?)");
                        $insert_stmt->bind_param("isis", $user_id, $override_date, $is_available, $reason);
                        if ($insert_stmt->execute()) {
                            $success_message = "Date availability set successfully!";
                        } else {
                            $error_message = "Failed to set date availability: " . $conn->error;
                        }
                    }
                }
                break;
                
            case 'update_booking_status':
                if (isset($_POST['booking_id'], $_POST['status']) && is_numeric($_POST['booking_id'])) {
                    $booking_id = intval($_POST['booking_id']);
                    $status = $_POST['status'];
                    $notes = trim($_POST['notes'] ?? '');
                    
                    // Verify booking belongs to this user
                    $check_stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND admin_id = ?");
                    $check_stmt->bind_param("ii", $booking_id, $user_id);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows === 0) {
                        $error_message = "Booking not found or you don't have permission to update it.";
                        break;
                    }
                    
                    // Update booking status
                    $stmt = $conn->prepare("UPDATE bookings SET status = ?, admin_notes = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $status, $notes, $booking_id);
                    if ($stmt->execute()) {
                        // If cancelled, update cancellation info
                        if ($status === 'cancelled') {
                            $cancel_stmt = $conn->prepare("UPDATE bookings SET cancellation_reason = ?, cancellation_date = NOW() WHERE id = ?");
                            $cancel_stmt->bind_param("si", $notes, $booking_id);
                            $cancel_stmt->execute();
                        }
                        $success_message = "Booking status updated successfully!";
                    } else {
                        $error_message = "Failed to update booking status: " . $conn->error;
                    }
                }
                break;
        }
    }
}

// Get timeslot configurations
$timeslots_sql = "SELECT * FROM timeslot_configurations WHERE user_id = ? ORDER BY day_of_week, start_time";
$stmt = $conn->prepare($timeslots_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$timeslots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch upcoming bookings
$upcoming_bookings_sql = "
    SELECT b.*, 
           u.email as user_email,
           vt.name as visa_type_name,
           c.name as country_name,
           st.name as service_type_name,
           cm.name as consultation_mode_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN visa_types vt ON b.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id 
    JOIN service_types st ON b.service_type_id = st.id
    JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
    WHERE b.booking_date >= CURDATE()
    AND (b.team_member_id IN (SELECT id FROM team_members WHERE user_id = ?) OR b.created_by = ?)
    ORDER BY b.booking_date, b.start_time
    LIMIT 20
";
$stmt = $conn->prepare($upcoming_bookings_sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_bookings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get date overrides for the next 30 days
$date_overrides_sql = "
    SELECT * FROM availability_overrides 
    WHERE user_id = ? AND date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date
";
$stmt = $conn->prepare($date_overrides_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$date_overrides = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch past bookings
$past_bookings_sql = "
    SELECT b.*, 
           u.email as user_email,
           vt.name as visa_type_name,
           c.name as country_name,
           st.name as service_type_name,
           cm.name as consultation_mode_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN visa_types vt ON b.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id 
    JOIN service_types st ON b.service_type_id = st.id
    JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
    WHERE b.booking_date < CURDATE()
    AND (b.team_member_id IN (SELECT id FROM team_members WHERE user_id = ?) OR b.created_by = ?)
    ORDER BY b.booking_date DESC, b.start_time DESC
    LIMIT 10
";
$stmt = $conn->prepare($past_bookings_sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$past_bookings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get services for dropdown
$services_sql = "
    SELECT id, name, country_id, visa_type_id 
    FROM services 
    WHERE admin_id = ? AND deleted_at IS NULL
    ORDER BY name
";
$stmt = $conn->prepare($services_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to get day name
function getDayName($dayNum) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$dayNum];
}

// Function to format time
function formatTime($timeStr) {
    $time = DateTime::createFromFormat('H:i:s', $timeStr);
    return $time ? $time->format('h:i A') : $timeStr;
}
?>

<div class="page-header">
    <div class="page-title">
        <h1><?php echo $page_header; ?></h1>
        <p>Manage your booking schedule and appointments</p>
    </div>
    <div class="page-actions">
        <button id="add-timeslot-btn" class="btn-secondary">
            <i class="fas fa-plus"></i> Add Timeslot
        </button>
        <button id="override-date-btn" class="btn-secondary">
            <i class="fas fa-calendar-alt"></i> Set Day Off
        </button>
        <?php if ($user_type === 'admin'): ?>
        <button id="update-capacity-btn" class="btn-secondary">
            <i class="fas fa-users"></i> Set Booking Capacity
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="close-btn"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        <button type="button" class="close-btn"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<div class="content-wrapper">
    <!-- Booking Info Banner -->
    <div class="booking-info-banner">
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="info-content">
                <h3>Upcoming Bookings</h3>
                <p><?php echo count($upcoming_bookings); ?></p>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="info-content">
                <h3>Team Timeslots</h3>
                <p><?php echo count($timeslots); ?></p>
            </div>
        </div>
        
        <?php if ($user_type === 'admin'): ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="info-content">
                <h3>Concurrent Bookings</h3>
                <p><?php echo $max_concurrent_bookings; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-card">
            <div class="info-icon booking-type-icon">
                <i class="fas <?php echo $user_type === 'member' ? 'fa-user' : 'fa-user-tie'; ?>"></i>
            </div>
            <div class="info-content">
                <h3>Account Type</h3>
                <p><?php echo ucfirst($user_type); ?></p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn active" data-tab="upcoming">Upcoming Bookings</button>
            <button class="tab-btn" data-tab="schedule">Team Availability</button>
            <button class="tab-btn" data-tab="history">Booking History</button>
        </div>
        
        <div class="tab-content active" id="upcoming-tab">
            <?php if (empty($upcoming_bookings)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <h3>No Upcoming Bookings</h3>
                    <p>There are no upcoming appointments scheduled.</p>
                </div>
            <?php else: ?>
                <div class="booking-list">
                    <?php
                    $current_date = '';
                    foreach ($upcoming_bookings as $booking):
                        $booking_date = new DateTime($booking['booking_date']);
                        if ($current_date !== $booking['booking_date']):
                            if ($current_date !== '') echo '</div>'; // Close previous date container
                            $current_date = $booking['booking_date'];
                    ?>
                    <div class="booking-date-group">
                        <div class="booking-date-header">
                            <h3><?php echo $booking_date->format('l, F j, Y'); ?></h3>
                        </div>
                    <?php endif; ?>
                        
                        <div class="booking-card" data-id="<?php echo $booking['id']; ?>">
                            <div class="booking-time">
                                <span><?php echo formatTime($booking['start_time']); ?> - <?php echo formatTime($booking['end_time']); ?></span>
                                <div class="booking-status <?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </div>
                            </div>
                            <div class="booking-details">
                                <h4><?php echo htmlspecialchars($booking['visa_type_name']); ?> - <?php echo htmlspecialchars($booking['service_type_name']); ?></h4>
                                <div class="booking-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-user"></i>
                                        <span><?php echo htmlspecialchars($booking['user_email']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-globe"></i>
                                        <span><?php echo htmlspecialchars($booking['country_name']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-comment"></i>
                                        <span><?php echo htmlspecialchars($booking['consultation_mode_name']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-tag"></i>
                                        <span>Ref: <?php echo htmlspecialchars($booking['reference_number'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="booking-actions">
                                <?php if ($booking['status'] === 'pending'): ?>
                                <button class="btn-confirm confirm-booking-btn" data-id="<?php echo $booking['id']; ?>">
                                    <i class="fas fa-check"></i> Confirm
                                </button>
                                <?php endif; ?>
                                <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                <button class="btn-edit reschedule-booking-btn" data-id="<?php echo $booking['id']; ?>">
                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                </button>
                                <button class="btn-cancel cancel-booking-btn" data-id="<?php echo $booking['id']; ?>">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                                <button class="btn-view view-booking-btn" data-id="<?php echo $booking['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div><!-- Close the last date container -->
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="schedule-tab">
            <div class="schedule-settings">
                <div class="schedule-card">
                    <div class="schedule-card-header">
                        <h3>Team Member Availability</h3>
                        <p>Manage your team's weekly availability slots.</p>
                    </div>
                    <div class="schedule-card-body">
                        <div class="actions-bar">
                            <button id="add-timeslot-btn" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Team Timeslot
                            </button>
                            <button id="override-date-btn" class="btn-secondary">
                                <i class="fas fa-calendar-alt"></i> Set Team Day Off
                            </button>
                        </div>
                        
                        <div class="weekly-schedule">
                            <?php if (empty($timeslots)): ?>
                                <div class="empty-schedule">
                                    <p>No team timeslots configured yet. Click "Add Team Timeslot" to set up your team's availability.</p>
                                </div>
                            <?php else: ?>
                                <?php
                                $days = [0 => [], 1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];
                                foreach ($timeslots as $slot) {
                                    $days[$slot['day_of_week']][] = $slot;
                                }
                                
                                foreach ($days as $day_num => $day_slots):
                                ?>
                                    <div class="day-schedule">
                                        <h4><?php echo getDayName($day_num); ?></h4>
                                        <?php if (empty($day_slots)): ?>
                                            <div class="no-slots">No slots configured</div>
                                        <?php else: ?>
                                            <div class="timeslot-list">
                                                <?php foreach ($day_slots as $slot): ?>
                                                    <div class="timeslot-item <?php echo $slot['is_active'] ? 'available' : 'unavailable'; ?>">
                                                        <div class="timeslot-time">
                                                            <?php echo formatTime($slot['start_time']); ?> - <?php echo formatTime($slot['end_time']); ?>
                                                        </div>
                                                        <div class="timeslot-details">
                                                            <div class="timeslot-member">
                                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($slot['team_member_name']); ?>
                                                            </div>
                                                            <div class="timeslot-duration">
                                                                <i class="fas fa-clock"></i> <?php echo $slot['slot_duration']; ?> min slots
                                                            </div>
                                                        </div>
                                                        <div class="timeslot-actions">
                                                            <button class="btn-edit edit-timeslot-btn" data-id="<?php echo $slot['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn-delete delete-timeslot-btn" data-id="<?php echo $slot['id']; ?>">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="schedule-card">
                    <div class="schedule-card-header">
                        <h3>Team Availability Overrides</h3>
                        <p>Manage custom availability for your team on specific dates.</p>
                    </div>
                    <div class="schedule-card-body">
                        <div id="overrides-container">
                            <!-- Overrides will be loaded by AJAX -->
                            <div class="loading">Loading team availability overrides...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tab-content" id="history-tab">
            <?php if (empty($past_bookings)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>No Past Bookings</h3>
                    <p>Booking history will appear here once appointments are completed.</p>
                </div>
            <?php else: ?>
                <div class="booking-history">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Client</th>
                                <th>Country</th>
                                <th>Visa Type</th>
                                <th>Service</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_bookings as $booking): 
                                $booking_date = new DateTime($booking['booking_date']);
                            ?>
                                <tr class="booking-history-row <?php echo strtolower($booking['status']); ?>">
                                    <td>
                                        <div class="history-date-time">
                                            <div class="history-date"><?php echo $booking_date->format('M j, Y'); ?></div>
                                            <div class="history-time"><?php echo formatTime($booking['start_time']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['country_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['visa_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['service_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['consultation_mode_name']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-view view-booking-btn" data-id="<?php echo $booking['id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Timeslot Modal -->
<div class="modal" id="timeslot-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="timeslot-modal-title">Add Team Member Timeslot</h2>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="timeslot-form" method="POST" action="">
                <input type="hidden" name="action" id="timeslot-form-action" value="add_timeslot">
                <input type="hidden" name="timeslot_id" id="timeslot-id" value="">
                
                <div class="form-group">
                    <label for="team-member">Team Member</label>
                    <select id="team-member" name="team_member_id" required>
                        <option value="">Select Team Member</option>
                        <!-- Team members will be loaded by AJAX -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="day-of-week">Day of Week</label>
                    <select id="day-of-week" name="day_of_week" required>
                        <option value="0">Sunday</option>
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start-time">Start Time</label>
                        <input type="time" id="start-time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end-time">End Time</label>
                        <input type="time" id="end-time" name="end_time" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="slot-duration">Slot Duration (minutes)</label>
                    <input type="number" id="slot-duration" name="slot_duration" min="15" max="240" value="60" required>
                    <small>Length of each appointment slot</small>
                </div>
                
                <div class="form-group">
                    <label for="slot-active">Status</label>
                    <div class="toggle-switch">
                        <input type="checkbox" id="slot-active" name="is_active" checked>
                        <label for="slot-active"></label>
                    </div>
                    <small>Active timeslots will be available for booking</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn-primary" id="save-timeslot-btn">Save Timeslot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Booking Modal -->
<div class="modal" id="view-booking-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Booking Details</h2>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="booking-details-container">
                <!-- Booking details will be loaded here via AJAX -->
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Loading booking details...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Dynamic Interaction -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Close alerts
    const closeButtons = document.querySelectorAll('.alert .close-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
    
    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Deactivate all tabs
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Activate selected tab
            this.classList.add('active');
            document.getElementById(tabId + '-tab').classList.add('active');
            
            // If we switched to schedule tab, load team members
            if (tabId === 'schedule') {
                loadTeamMembers();
            }
        });
    });
    
    // Load team members for timeslot form
    function loadTeamMembers() {
        const teamMemberSelect = document.getElementById('team-member');
        if (teamMemberSelect.options.length <= 1) {
            fetch('ajax/get_team_members.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.team_members.forEach(member => {
                            const option = document.createElement('option');
                            option.value = member.id;
                            option.textContent = member.name;
                            teamMemberSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading team members:', error));
        }
    }
    
    // Show timeslot modal
    const addTimeslotBtn = document.getElementById('add-timeslot-btn');
    const timeslotModal = document.getElementById('timeslot-modal');
    
    addTimeslotBtn?.addEventListener('click', function() {
        // Reset form
        document.getElementById('timeslot-form').reset();
        document.getElementById('timeslot-form-action').value = 'add_timeslot';
        document.getElementById('timeslot-id').value = '';
        document.getElementById('timeslot-modal-title').textContent = 'Add Team Member Timeslot';
        
        // Show modal
        timeslotModal.classList.add('show');
    });
    
    // Show date override modal
    const overrideDateBtn = document.getElementById('override-date-btn');
    const overrideModal = document.getElementById('override-modal');
    
    overrideDateBtn?.addEventListener('click', function() {
        // Reset form
        document.getElementById('override-form').reset();
        document.getElementById('override-id').value = '';
        
        // Set minimum date to today
        const dateInput = document.getElementById('override_date');
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
        dateInput.value = today;
        
        // Show modal
        overrideModal.classList.add('show');
    });
    
    // Show capacity modal
    const updateCapacityBtn = document.getElementById('update-capacity-btn');
    const capacityEditBtn = document.getElementById('capacity-edit-btn');
    const capacityModal = document.getElementById('capacity-modal');
    
    [updateCapacityBtn, capacityEditBtn].forEach(btn => {
        btn?.addEventListener('click', function() {
            // Show modal
            capacityModal.classList.add('show');
        });
    });
    
    // Confirm booking
    const confirmBookingBtns = document.querySelectorAll('.confirm-booking-btn');
    confirmBookingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'confirmed';
            document.getElementById('status-modal-title').textContent = 'Confirm Booking';
            document.getElementById('status-message').innerHTML = 'Are you sure you want to confirm this booking? This will notify the client that their appointment is confirmed.';
            document.getElementById('update-status-btn').textContent = 'Confirm Booking';
            document.getElementById('update-status-modal').classList.add('show');
        });
    });
    
    // Cancel booking
    const cancelBookingBtns = document.querySelectorAll('.cancel-booking-btn');
    cancelBookingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'cancelled';
            document.getElementById('status-modal-title').textContent = 'Cancel Booking';
            document.getElementById('status-message').innerHTML = '<strong class="text-danger">Warning: This will cancel the booking.</strong> Please provide a reason for the cancellation:';
            document.getElementById('update-status-btn').textContent = 'Cancel Booking';
            document.getElementById('update-status-modal').classList.add('show');
        });
    });
    
    // View booking details
    const viewBookingBtns = document.querySelectorAll('.view-booking-btn');
    viewBookingBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            const detailsContainer = document.getElementById('booking-details-container');
            
            // Show loading state
            detailsContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading booking details...</div>';
            document.getElementById('view-booking-modal').classList.add('show');
            
            // Fetch booking details
            fetch(`ajax/get_booking.php?booking_id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayBookingDetails(data.booking);
                    } else {
                        detailsContainer.innerHTML = `<div class="error-message">${data.error || 'Error loading booking details'}</div>`;
                    }
                })
                .catch(error => {
                    detailsContainer.innerHTML = '<div class="error-message">Failed to load booking details. Please try again.</div>';
                });
        });
    });
    
    function displayBookingDetails(booking) {
        const detailsContainer = document.getElementById('booking-details-container');
        
        // Format date and time
        const bookingDate = new Date(booking.booking_date);
        const formattedDate = bookingDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        // Build HTML for details
        let html = `
            <div class="booking-detail-header">
                <div class="detail-reference">Ref: ${booking.reference_number}</div>
                <div class="detail-status ${booking.status}">${booking.status}</div>
            </div>
            
            <div class="booking-detail-section">
                <h3>Appointment Details</h3>
                <div class="detail-item">
                    <div class="detail-label">Date</div>
                    <div class="detail-value">${formattedDate}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Time</div>
                    <div class="detail-value">${formatTime(booking.start_time)} - ${formatTime(booking.end_time)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Service</div>
                    <div class="detail-value">${booking.visa_type_name} - ${booking.service_type_name}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Consultation Mode</div>
                    <div class="detail-value">${booking.consultation_mode_name}</div>
                </div>
            </div>
            
            <div class="booking-detail-section">
                <h3>Client Information</h3>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value">${booking.user_email}</div>
                </div>
            </div>
        `;
        
        // Add notes if available
        if (booking.notes) {
            html += `
                <div class="booking-detail-section">
                    <h3>Client Notes</h3>
                    <div class="detail-notes">${booking.notes}</div>
                </div>
            `;
        }
        
        // Add professional notes if available
        if (booking.professional_notes) {
            html += `
                <div class="booking-detail-section">
                    <h3>Your Notes</h3>
                    <div class="detail-notes">${booking.professional_notes}</div>
                </div>
            `;
        }
        
        // Add cancellation info if cancelled
        if (booking.status === 'cancelled') {
            html += `
                <div class="booking-detail-section">
                    <h3>Cancellation Details</h3>
                    <div class="detail-item">
                        <div class="detail-label">Cancelled On</div>
                        <div class="detail-value">${new Date(booking.cancellation_date).toLocaleString()}</div>
                    </div>
                    ${booking.cancellation_reason ? `
                    <div class="detail-item">
                        <div class="detail-label">Reason</div>
                        <div class="detail-value">${booking.cancellation_reason}</div>
                    </div>` : ''}
                </div>
            `;
        }
        
        // Add action buttons at bottom
        html += `
            <div class="booking-detail-actions">
                ${(booking.status === 'pending') ? `
                <button class="btn-confirm confirm-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-check"></i> Confirm
                </button>` : ''}
                
                ${(booking.status === 'pending' || booking.status === 'confirmed') ? `
                <button class="btn-edit reschedule-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-calendar-alt"></i> Reschedule
                </button>
                <button class="btn-cancel cancel-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-times"></i> Cancel
                </button>` : ''}
                
                ${(booking.status === 'confirmed' && new Date(`${booking.booking_date} ${booking.start_time}`) <= new Date()) ? `
                <button class="btn-complete complete-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-check-circle"></i> Mark Completed
                </button>
                <button class="btn-noshow noshow-from-detail-btn" data-id="${booking.id}">
                    <i class="fas fa-user-slash"></i> No Show
                </button>` : ''}
            </div>
        `;
        
        detailsContainer.innerHTML = html;
        
        // Add event listeners to new buttons
        document.querySelector('.confirm-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'confirmed';
            document.getElementById('status-modal-title').textContent = 'Confirm Booking';
            document.getElementById('status-message').innerHTML = 'Are you sure you want to confirm this booking? This will notify the client that their appointment is confirmed.';
            document.getElementById('update-status-btn').textContent = 'Confirm Booking';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
        
        document.querySelector('.cancel-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'cancelled';
            document.getElementById('status-modal-title').textContent = 'Cancel Booking';
            document.getElementById('status-message').innerHTML = '<strong class="text-danger">Warning: This will cancel the booking.</strong> Please provide a reason for the cancellation:';
            document.getElementById('update-status-btn').textContent = 'Cancel Booking';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
        
        document.querySelector('.complete-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'completed';
            document.getElementById('status-modal-title').textContent = 'Complete Booking';
            document.getElementById('status-message').innerHTML = 'Mark this booking as completed? Add any notes about the appointment:';
            document.getElementById('update-status-btn').textContent = 'Mark Completed';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
        
        document.querySelector('.noshow-from-detail-btn')?.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-id');
            document.getElementById('status-booking-id').value = bookingId;
            document.getElementById('booking-status').value = 'no_show';
            document.getElementById('status-modal-title').textContent = 'Mark as No Show';
            document.getElementById('status-message').innerHTML = 'Mark this client as a no-show? Add any notes:';
            document.getElementById('update-status-btn').textContent = 'Mark as No Show';
            document.getElementById('view-booking-modal').classList.remove('show');
            document.getElementById('update-status-modal').classList.add('show');
        });
    }
    
    // Helper function to format time
    function formatTime(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours);
        const amPm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${amPm}`;
    }
    
    // Close modals
    const modalCloseButtons = document.querySelectorAll('.modal-close, .modal-cancel');
    
    modalCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.classList.remove('show');
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
