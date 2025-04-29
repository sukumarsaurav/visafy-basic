<?php
$page_title = "Bookings Management";
$page_specific_css = "assets/css/bookings.css";
require_once 'includes/header.php';

// Handle booking assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_booking'])) {
    $booking_id = $_POST['booking_id'];
    $team_member_id = $_POST['team_member_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $admin_notes = $_POST['admin_notes'];
    
    // Validate inputs
    if (empty($team_member_id) || empty($booking_date) || empty($start_time) || empty($end_time)) {
        $error_message = "All fields are required";
    } else {
        // Check if team member is available at the selected time
        $check_query = "SELECT COUNT(*) as count FROM bookings 
                      WHERE team_member_id = ? 
                      AND booking_date = ? 
                      AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))
                      AND status NOT IN ('cancelled', 'no_show')
                      AND id != ?";
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('isssssssi', $team_member_id, $booking_date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time, $booking_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $conflicting_bookings = $check_result->fetch_assoc()['count'];
        
        if ($conflicting_bookings > 0) {
            $error_message = "The selected team member is not available at this time";
        } else {
            // Update booking
            $prev_team_member_query = "SELECT team_member_id FROM bookings WHERE id = ?";
            $prev_stmt = $conn->prepare($prev_team_member_query);
            $prev_stmt->bind_param('i', $booking_id);
            $prev_stmt->execute();
            $prev_result = $prev_stmt->get_result();
            $prev_team_member = $prev_result->fetch_assoc()['team_member_id'];
            
            $update_query = "UPDATE bookings SET 
                          team_member_id = ?,
                          booking_date = ?,
                          start_time = ?,
                          end_time = ?,
                          admin_notes = ?,
                          status = 'assigned'
                          WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('issssi', $team_member_id, $booking_date, $start_time, $end_time, $admin_notes, $booking_id);
            
            if ($update_stmt->execute()) {
                // Record assignment history
                $history_query = "INSERT INTO booking_assignment_history 
                              (booking_id, admin_id, team_member_id, previous_team_member_id, notes) 
                              VALUES (?, ?, ?, ?, ?)";
                
                $history_stmt = $conn->prepare($history_query);
                $admin_id = $_SESSION['user_id'];
                $history_stmt->bind_param('iiiis', $booking_id, $admin_id, $team_member_id, $prev_team_member, $admin_notes);
                $history_stmt->execute();
                
                // Log activity
                $activity_type = $prev_team_member ? 'reassigned' : 'assigned';
                $activity_query = "INSERT INTO booking_activity_logs 
                               (booking_id, user_id, user_type, activity_type, description) 
                               VALUES (?, ?, 'admin', ?, ?)";
                
                $description = "Booking " . ($prev_team_member ? "reassigned" : "assigned") . " to team member ID $team_member_id";
                $activity_stmt = $conn->prepare($activity_query);
                $activity_stmt->bind_param('iiss', $booking_id, $admin_id, $activity_type, $description);
                $activity_stmt->execute();
                
                $success_message = "Booking successfully assigned";
                
                // Get filter parameters for redirection
                $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
                
                // Redirect to prevent form resubmission
                header("Location: bookings.php?status=$status_filter");
                exit;
            } else {
                $error_message = "Error assigning booking: " . $conn->error;
            }
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $new_status = $_POST['new_status'];
    
    $update_query = "UPDATE bookings SET status = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('si', $new_status, $booking_id);
    
    if ($update_stmt->execute()) {
        // Log activity
        $activity_query = "INSERT INTO booking_activity_logs 
                        (booking_id, user_id, user_type, activity_type, description) 
                        VALUES (?, ?, 'admin', 'status_changed', ?)";
        
        $admin_id = $_SESSION['user_id'];
        $description = "Booking status changed to $new_status";
        $activity_stmt = $conn->prepare($activity_query);
        $activity_stmt->bind_param('iis', $booking_id, $admin_id, $description);
        $activity_stmt->execute();
        
        $success_message = "Booking status updated successfully";
        
        // Get filter parameters for redirection
        $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
        
        // Redirect to prevent form resubmission
        header("Location: bookings.php?status=$status_filter");
        exit;
    } else {
        $error_message = "Error updating status: " . $conn->error;
    }
}

// After all header redirects, include the header file
require_once 'includes/header.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the base query
$query = "SELECT b.*, 
          CONCAT(u.first_name, ' ', u.last_name) as applicant_name,
          vt.name as visa_type, 
          st.name as service_type,
          cm.name as consultation_mode,
          CONCAT(tm_u.first_name, ' ', tm_u.last_name) as team_member_name
          FROM bookings b
          LEFT JOIN users u ON b.user_id = u.id
          LEFT JOIN visa_types vt ON b.visa_type_id = vt.id
          LEFT JOIN service_types st ON b.service_type_id = st.id
          LEFT JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
          LEFT JOIN team_members tm ON b.team_member_id = tm.id
          LEFT JOIN users tm_u ON tm.user_id = tm_u.id
          WHERE 1=1";

// Add filters
if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
}

if (!empty($date_from)) {
    $query .= " AND b.booking_date >= ?";
}

if (!empty($date_to)) {
    $query .= " AND b.booking_date <= ?";
}

if (!empty($search)) {
    $query .= " AND (b.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR vt.name LIKE ?)";
}

$query .= " ORDER BY b.created_at DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($query);

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
$team_members_query = "SELECT tm.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email
                      FROM team_members tm
                      JOIN users u ON tm.user_id = u.id
                      WHERE u.status = 'active'
                      ORDER BY u.first_name, u.last_name";
$team_members_result = $conn->query($team_members_query);
$team_members = $team_members_result->fetch_all(MYSQLI_ASSOC);
?>

<div class="content">
    <h1>Bookings Management</h1>
    <p>Manage booking assignments, schedules, and status updates for client consultations.</p>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Filters Section -->
    <div class="section">
        <h2>Filter Bookings</h2>
        <div class="filter-card">
            <form action="bookings.php" method="GET" class="filter-form">
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
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Reference, name, visa type..." value="<?php echo $search; ?>">
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn filter-btn">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="bookings.php" class="btn reset-btn">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bookings Section -->
    <div class="section">
        <h2>Bookings</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Applicant</th>
                        <th>Visa Type</th>
                        <th>Service</th>
                        <th>Mode</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Team Member</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bookings) > 0): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr class="booking-row status-<?php echo $booking['status']; ?>">
                                <td><?php echo htmlspecialchars($booking['reference_number']); ?></td>
                                <td><?php echo htmlspecialchars($booking['applicant_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['visa_type']); ?></td>
                                <td><?php echo htmlspecialchars($booking['service_type']); ?></td>
                                <td><?php echo htmlspecialchars($booking['consultation_mode']); ?></td>
                                <td>
                                    <?php if ($booking['booking_date']): ?>
                                        <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?><br>
                                        <?php echo date('h:i A', strtotime($booking['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($booking['end_time'])); ?>
                                    <?php else: ?>
                                        <span class="badge warning">Not Scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $booking['status']; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($booking['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $booking['payment_status']; ?>">
                                        <?php echo ucfirst($booking['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['team_member_name']): ?>
                                        <?php echo htmlspecialchars($booking['team_member_name']); ?>
                                    <?php else: ?>
                                        <span class="badge warning">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button class="action-btn view-btn" onclick="location.href='view_booking.php?id=<?php echo $booking['id']; ?>'">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn assign-btn" data-booking-id="<?php echo $booking['id']; ?>"
                                        data-booking-reference="<?php echo $booking['reference_number']; ?>"
                                        data-booking-date="<?php echo $booking['booking_date']; ?>"
                                        data-booking-start="<?php echo $booking['start_time']; ?>"
                                        data-booking-end="<?php echo $booking['end_time']; ?>"
                                        data-booking-team-member="<?php echo $booking['team_member_id']; ?>"
                                        data-booking-notes="<?php echo htmlspecialchars($booking['admin_notes']); ?>">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <button class="action-btn status-btn" 
                                        data-booking-id="<?php echo $booking['id']; ?>"
                                        data-booking-reference="<?php echo $booking['reference_number']; ?>"
                                        data-current-status="<?php echo $booking['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn payment-btn" onclick="location.href='booking_payments.php?id=<?php echo $booking['id']; ?>'">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="no-data">No bookings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for assigning a booking -->
<div class="modal" id="assign-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Assign Booking</h2>
        <form id="assign-booking-form" method="POST" action="bookings.php">
            <input type="hidden" name="booking_id" id="modal-booking-id">
            
            <div class="form-group">
                <label for="booking_reference">Booking Reference</label>
                <input type="text" class="form-control" id="booking_reference" readonly>
            </div>
            
            <div class="form-group">
                <label for="team_member_id">Assign to Team Member*</label>
                <select name="team_member_id" id="team_member_id" class="form-control" required>
                    <option value="">-- Select Team Member --</option>
                    <?php foreach ($team_members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['name']); ?> (<?php echo htmlspecialchars($member['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="booking_date">Date*</label>
                    <input type="date" name="booking_date" id="booking_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="start_time">Start Time*</label>
                    <input type="time" name="start_time" id="start_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Time*</label>
                    <input type="time" name="end_time" id="end_time" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="admin_notes">Admin Notes</label>
                <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-buttons">
                <button type="button" class="btn cancel-btn" id="cancel-assign">Cancel</button>
                <button type="submit" name="assign_booking" class="btn submit-btn">Assign Booking</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for updating status -->
<div class="modal" id="status-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Update Booking Status</h2>
        <form id="update-status-form" method="POST" action="bookings.php">
            <input type="hidden" name="booking_id" id="status-booking-id">
            
            <div class="form-group">
                <label>Booking Reference: <span id="status-booking-reference"></span></label>
            </div>
            
            <div class="form-group">
                <label for="new_status">New Status*</label>
                <select name="new_status" id="new_status" class="form-control" required>
                    <option value="pending_assignment">Pending Assignment</option>
                    <option value="assigned">Assigned</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="completed">Completed</option>
                    <option value="no_show">No Show</option>
                </select>
            </div>
            
            <div class="form-buttons">
                <button type="button" class="btn cancel-btn" id="cancel-status">Cancel</button>
                <button type="submit" name="update_status" class="btn submit-btn">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functionality
const assignModal = document.getElementById('assign-modal');
const statusModal = document.getElementById('status-modal');
const closeButtons = document.querySelectorAll('.close');
const cancelAssignBtn = document.getElementById('cancel-assign');
const cancelStatusBtn = document.getElementById('cancel-status');

// Open assign modal
document.querySelectorAll('.assign-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const bookingId = this.dataset.bookingId;
        const reference = this.dataset.bookingReference;
        const date = this.dataset.bookingDate;
        const start = this.dataset.bookingStart;
        const end = this.dataset.bookingEnd;
        const teamMember = this.dataset.bookingTeamMember;
        const notes = this.dataset.bookingNotes;
        
        document.getElementById('modal-booking-id').value = bookingId;
        document.getElementById('booking_reference').value = reference;
        document.getElementById('booking_date').value = date;
        document.getElementById('start_time').value = start;
        document.getElementById('end_time').value = end;
        document.getElementById('team_member_id').value = teamMember;
        document.getElementById('admin_notes').value = notes;
        
        assignModal.style.display = 'block';
    });
});

// Open status modal
document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const bookingId = this.dataset.bookingId;
        const reference = this.dataset.bookingReference;
        const currentStatus = this.dataset.currentStatus;
        
        document.getElementById('status-booking-id').value = bookingId;
        document.getElementById('status-booking-reference').textContent = reference;
        document.getElementById('new_status').value = currentStatus;
        
        statusModal.style.display = 'block';
    });
});

// Close buttons functionality
closeButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Cancel buttons
if (cancelAssignBtn) {
    cancelAssignBtn.addEventListener('click', function() {
        assignModal.style.display = 'none';
    });
}

if (cancelStatusBtn) {
    cancelStatusBtn.addEventListener('click', function() {
        statusModal.style.display = 'none';
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === assignModal) {
        assignModal.style.display = 'none';
    }
    if (event.target === statusModal) {
        statusModal.style.display = 'none';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
