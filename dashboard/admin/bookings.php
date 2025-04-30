<?php
$page_title = "Bookings Management";
$page_specific_js = "assets/js/bookings.js";
$page_specific_css = "assets/css/bookings.css";
require_once 'includes/header.php';

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo "<div class='alert alert-danger'>You don't have permission to access this page.</div>";
    require_once 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['id'];

// Handle form submissions
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process different form actions
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_booking_status':
                if (isset($_POST['booking_id'], $_POST['status']) && is_numeric($_POST['booking_id'])) {
                    $booking_id = intval($_POST['booking_id']);
                    $status = $_POST['status'];
                    $notes = trim($_POST['notes'] ?? '');
                    
                    // Update booking status
                    $stmt = $conn->prepare("UPDATE bookings SET status = ?, team_member_notes = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $status, $notes, $booking_id);
                    if ($stmt->execute()) {
                        // If cancelled, update cancellation info
                        if ($status === 'cancelled') {
                            $cancel_stmt = $conn->prepare("UPDATE bookings SET cancellation_reason = ?, cancellation_date = NOW() WHERE id = ?");
                            $cancel_stmt->bind_param("si", $notes, $booking_id);
                            $cancel_stmt->execute();
                        }
                        
                        // Log activity
                        $activity_query = "INSERT INTO booking_activity_logs 
                                       (booking_id, user_id, user_type, activity_type, description) 
                                       VALUES (?, ?, 'admin', 'status_changed', ?)";
                        
                        $description = "Booking status changed to $status";
                        $activity_stmt = $conn->prepare($activity_query);
                        $activity_stmt->bind_param('iis', $booking_id, $user_id, $description);
                        $activity_stmt->execute();
                        
                        $success_message = "Booking status updated successfully!";
                    } else {
                        $error_message = "Failed to update booking status: " . $conn->error;
                    }
                }
                break;
                
            case 'assign_team_member':
                if (isset($_POST['booking_id'], $_POST['team_member_id'])) {
                    $booking_id = intval($_POST['booking_id']);
                    $team_member_id = intval($_POST['team_member_id']);
                    $notes = trim($_POST['admin_notes'] ?? '');
                    
                    // Get previous team member
                    $prev_stmt = $conn->prepare("SELECT team_member_id FROM bookings WHERE id = ?");
                    $prev_stmt->bind_param("i", $booking_id);
                    $prev_stmt->execute();
                    $prev_result = $prev_stmt->get_result();
                    $prev_team_member = $prev_result->fetch_assoc()['team_member_id'];
                    
                    // Update booking
                    $stmt = $conn->prepare("UPDATE bookings SET team_member_id = ?, admin_notes = ? WHERE id = ?");
                    $stmt->bind_param("isi", $team_member_id, $notes, $booking_id);
                    
                    if ($stmt->execute()) {
                        // Log activity and history
                        $activity_type = $prev_team_member ? 'reassigned' : 'assigned';
                        $activity_query = "INSERT INTO booking_activity_logs 
                                       (booking_id, user_id, user_type, activity_type, description) 
                                       VALUES (?, ?, 'admin', ?, ?)";
                        
                        $description = "Booking " . ($prev_team_member ? "reassigned" : "assigned") . " to team member ID $team_member_id";
                        $activity_stmt = $conn->prepare($activity_query);
                        $activity_stmt->bind_param('iiss', $booking_id, $user_id, $activity_type, $description);
                        $activity_stmt->execute();
                        
                        // Add to assignment history
                        $history_query = "INSERT INTO booking_assignment_history 
                                      (booking_id, admin_id, team_member_id, previous_team_member_id, notes) 
                                      VALUES (?, ?, ?, ?, ?)";
                        
                        $history_stmt = $conn->prepare($history_query);
                        $history_stmt->bind_param('iiiis', $booking_id, $user_id, $team_member_id, $prev_team_member, $notes);
                        $history_stmt->execute();
                        
                        $success_message = "Team member assigned successfully!";
                    } else {
                        $error_message = "Failed to assign team member: " . $conn->error;
                    }
                }
                break;
                
            case 'reschedule_booking':
                if (isset($_POST['booking_id'], $_POST['booking_date'], $_POST['start_time'], $_POST['end_time'])) {
                    $booking_id = intval($_POST['booking_id']);
                    $booking_date = $_POST['booking_date'];
                    $start_time = $_POST['start_time'];
                    $end_time = $_POST['end_time'];
                    $notes = trim($_POST['notes'] ?? '');
                    
                    // Update booking
                    $stmt = $conn->prepare("UPDATE bookings SET booking_date = ?, start_time = ?, end_time = ?, admin_notes = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $booking_date, $start_time, $end_time, $notes, $booking_id);
                    
                    if ($stmt->execute()) {
                        // Log activity
                        $activity_query = "INSERT INTO booking_activity_logs 
                                       (booking_id, user_id, user_type, activity_type, description) 
                                       VALUES (?, ?, 'admin', 'rescheduled', ?)";
                        
                        $description = "Booking rescheduled to $booking_date at $start_time";
                        $activity_stmt = $conn->prepare($activity_query);
                        $activity_stmt->bind_param('iis', $booking_id, $user_id, $description);
                        $activity_stmt->execute();
                        
                        $success_message = "Booking rescheduled successfully!";
                    } else {
                        $error_message = "Failed to reschedule booking: " . $conn->error;
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get bookings data
$current_datetime = date('Y-m-d H:i:s');
$bookings_sql = "
    SELECT b.*,
           u.email as user_email,
           CONCAT(u.first_name, ' ', u.last_name) as client_name,
           vt.name as visa_type,
           st.name as service_type,
           cm.name as consultation_mode,
           CONCAT(tm_u.first_name, ' ', tm_u.last_name) as team_member_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN visa_types vt ON b.visa_type_id = vt.id
    JOIN service_types st ON b.service_type_id = st.id
    JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
    LEFT JOIN team_members tm ON b.team_member_id = tm.id
    LEFT JOIN users tm_u ON tm.user_id = tm_u.id
    WHERE 1=1
";

// Add filters
if ($status_filter !== 'all') {
    $bookings_sql .= " AND b.status = ?";
}

if (!empty($date_from)) {
    $bookings_sql .= " AND b.booking_date >= ?";
}

if (!empty($date_to)) {
    $bookings_sql .= " AND b.booking_date <= ?";
}

if (!empty($search)) {
    $bookings_sql .= " AND (b.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR vt.name LIKE ?)";
}

$bookings_sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($bookings_sql);

// Bind parameters
$paramTypes = '';
$paramValues = [];

if ($status_filter !== 'all') {
    $paramTypes .= 's';
    $paramValues[] = $status_filter;
}

if (!empty($date_from)) {
    $paramTypes .= 's';
    $paramValues[] = $date_from;
}

if (!empty($date_to)) {
    $paramTypes .= 's';
    $paramValues[] = $date_to;
}

if (!empty($search)) {
    $paramTypes .= 'sss';
    $paramValues[] = "%$search%";
    $paramValues[] = "%$search%";
    $paramValues[] = "%$search%";
}

if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$paramValues);
}

$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Get team members for assignment dropdown
$team_members = [];
$team_sql = "SELECT tm.id, u.first_name, u.last_name, u.email, tm.role 
             FROM team_members tm
             JOIN users u ON tm.user_id = u.id
             WHERE tm.deleted_at IS NULL
             ORDER BY u.first_name, u.last_name";
$team_result = $conn->query($team_sql);
$team_members = $team_result->fetch_all(MYSQLI_ASSOC);

// Function to format time
function formatTime($timeStr) {
    $time = DateTime::createFromFormat('H:i:s', $timeStr);
    return $time ? $time->format('h:i A') : $timeStr;
}
?>

<div class="page-header">
    <div class="page-title">
        <h1><?php echo $page_title; ?></h1>
        <p>Manage booking appointments and schedule for your clients</p>
    </div>
    <div class="page-actions">
        <button id="filter-toggle-btn" class="btn-secondary">
            <i class="fas fa-filter"></i> Filters
        </button>
        <button id="view-team-btn" class="btn-secondary" onclick="location.href='team_members.php'">
            <i class="fas fa-users"></i> View Team
        </button>
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
    <!-- Filter Section -->
    <div class="filter-section" id="filter-section">
        <div class="filter-card">
            <form action="bookings.php" method="GET" class="filter-form">
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending_assignment" <?php echo $status_filter === 'pending_assignment' ? 'selected' : ''; ?>>Pending Assignment</option>
                            <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="no_show" <?php echo $status_filter === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                </div>
                <div class="form-group-row">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Reference, name or service..." value="<?php echo $search; ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="bookings.php" class="btn-outline">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Booking Info Banner -->
    <div class="booking-info-banner">
        <?php
        $upcoming_count = 0;
        $today_count = 0;
        $pending_count = 0;
        $completed_count = 0;
        
        foreach ($bookings as $booking) {
            $booking_date = new DateTime($booking['booking_date']);
            $today = new DateTime('today');
            
            if ($booking_date > $today && in_array($booking['status'], ['pending_assignment', 'assigned', 'confirmed'])) {
                $upcoming_count++;
            }
            
            if ($booking_date->format('Y-m-d') === $today->format('Y-m-d')) {
                $today_count++;
            }
            
            if ($booking['status'] === 'pending_assignment') {
                $pending_count++;
            }
            
            if ($booking['status'] === 'completed') {
                $completed_count++;
            }
        }
        ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="info-content">
                <h3>Upcoming</h3>
                <p><?php echo $upcoming_count; ?></p>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="info-content">
                <h3>Today</h3>
                <p><?php echo $today_count; ?></p>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="info-content">
                <h3>Pending</h3>
                <p><?php echo $pending_count; ?></p>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="info-content">
                <h3>Completed</h3>
                <p><?php echo $completed_count; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Bookings Table -->
    <div class="booking-list">
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <h3>No Bookings Found</h3>
                <p>No bookings match your current filters.</p>
            </div>
        <?php else: ?>
            <?php
            $current_date = '';
            foreach ($bookings as $booking):
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
                            <?php echo str_replace('_', ' ', ucwords($booking['status'])); ?>
                        </div>
                    </div>
                    <div class="booking-details">
                        <h4><?php echo htmlspecialchars($booking['visa_type']); ?> - <?php echo htmlspecialchars($booking['service_type']); ?></h4>
                        <div class="booking-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($booking['client_name']); ?> (<?php echo htmlspecialchars($booking['user_email']); ?>)</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-comment"></i>
                                <span><?php echo htmlspecialchars($booking['consultation_mode']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <span>Ref: <?php echo htmlspecialchars($booking['reference_number']); ?></span>
                            </div>
                            <?php if (!empty($booking['team_member_name'])): ?>
                            <div class="meta-item">
                                <i class="fas fa-user-tie"></i>
                                <span>Assigned to: <?php echo htmlspecialchars($booking['team_member_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="booking-actions">
                        <?php if ($booking['status'] === 'pending_assignment' || $booking['status'] === 'assigned'): ?>
                        <button class="btn-confirm confirm-booking-btn" data-id="<?php echo $booking['id']; ?>">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                        <?php endif; ?>
                        <?php if (in_array($booking['status'], ['pending_assignment', 'assigned', 'confirmed'])): ?>
                        <button class="btn-edit reschedule-booking-btn" data-id="<?php echo $booking['id']; ?>">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </button>
                        <button class="btn-cancel cancel-booking-btn" data-id="<?php echo $booking['id']; ?>">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <?php endif; ?>
                        <?php if ($booking['status'] === 'pending_assignment' || ($booking['status'] === 'assigned' && empty($booking['team_member_id']))): ?>
                        <button class="btn-assign assign-team-btn" data-id="<?php echo $booking['id']; ?>">
                            <i class="fas fa-user-plus"></i> Assign
                        </button>
                        <?php endif; ?>
                        <button class="btn-view view-booking-btn" data-id="<?php echo $booking['id']; ?>">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            </div><!-- Close the last date container -->
        <?php endif; ?>
    </div>
</div>

<!-- Modals -->
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

<!-- Update Booking Status Modal -->
<div class="modal" id="update-status-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="status-modal-title">Update Booking Status</h2>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="update-status-form" method="POST" action="">
                <input type="hidden" name="action" value="update_booking_status">
                <input type="hidden" name="booking_id" id="status-booking-id" value="">
                <input type="hidden" name="status" id="booking-status" value="">
                
                <div class="status-message" id="status-message"></div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn-primary" id="update-status-btn">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reschedule Booking Modal -->
<div class="modal" id="reschedule-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Reschedule Booking</h2>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="reschedule-form" method="POST" action="">
                <input type="hidden" name="action" value="reschedule_booking">
                <input type="hidden" name="booking_id" id="reschedule-booking-id" value="">
                
                <div class="form-group">
                    <label for="booking_date">New Date</label>
                    <input type="date" id="booking_date" name="booking_date" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reschedule_notes">Notes</label>
                    <textarea id="reschedule_notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Reschedule Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Team Member Modal -->
<div class="modal" id="assign-team-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Team Member</h2>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="assign-team-form" method="POST" action="">
                <input type="hidden" name="action" value="assign_team_member">
                <input type="hidden" name="booking_id" id="assign-booking-id" value="">
                
                <div class="form-group">
                    <label for="team_member_id">Team Member</label>
                    <select id="team_member_id" name="team_member_id" required>
                        <option value="">-- Select Team Member --</option>
                        <?php foreach ($team_members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> 
                            (<?php echo htmlspecialchars($member['role']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="admin_notes">Assignment Notes</label>
                    <textarea id="admin_notes" name="admin_notes" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn-primary">Assign Team Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
