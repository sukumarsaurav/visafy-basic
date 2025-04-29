<?php
$page_title = "Client Management";
require_once 'includes/header.php';


// Function to get booking status counts for a client
function getClientStatusCounts($conn, $clientId) {
    $counts = [
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    $query = "SELECT status, COUNT(*) as count FROM bookings WHERE user_id = ? GROUP BY status";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $clientId);
    $stmt->execute();
    $statusResults = $stmt->get_result();
    
    while ($row = $statusResults->fetch_assoc()) {
        $status = $row['status'];
        if (isset($counts[$status])) {
            $counts[$status] = $row['count'];
        }
    }
    
    return $counts;
}

// Get all clients who have made bookings
$query = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.status,
           COUNT(b.id) as total_bookings,
           MAX(b.created_at) as last_booking_date
         FROM users u
         JOIN bookings b ON u.id = b.user_id
         WHERE u.user_type = 'applicant'
         GROUP BY u.id
         ORDER BY last_booking_date DESC";
$result = $conn->query($query);

// Initialize messages from session
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<div class="content">
    <h1>Client Management</h1>
    <p>Manage client accounts and view client booking history.</p>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table id="clientsTable" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Total Bookings</th>
                    <th>Status</th>
                    <th>Last Booking</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                while ($client = $result->fetch_assoc()): 
                    $statusCounts = getClientStatusCounts($conn, $client['id']);
                ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($client['email']); ?></td>
                        <td>
                            <span class="badge bg-primary"><?php echo $client['total_bookings']; ?></span>
                            <div class="small mt-1">
                                <span class="text-warning">Pending: <?php echo $statusCounts['pending']; ?></span>,
                                <span class="text-info">In Progress: <?php echo $statusCounts['in_progress']; ?></span>,
                                <span class="text-success">Completed: <?php echo $statusCounts['completed']; ?></span>,
                                <span class="text-danger">Cancelled: <?php echo $statusCounts['cancelled']; ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($client['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ($client['status'] === 'suspended'): ?>
                                <span class="badge bg-danger">Suspended</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($client['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($client['last_booking_date'])); ?></td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="actionDropdown<?php echo $client['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                    Actions
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="actionDropdown<?php echo $client['id']; ?>">
                                    <li><a class="dropdown-item" href="view-client.php?id=<?php echo $client['id']; ?>">View Details</a></li>
                                    <?php if ($client['status'] === 'active'): ?>
                                        <li><a class="dropdown-item text-danger" href="client-status.php?id=<?php echo $client['id']; ?>&action=suspend" onclick="return confirm('Are you sure you want to suspend this client account?')">Suspend Account</a></li>
                                    <?php elseif ($client['status'] === 'suspended'): ?>
                                        <li><a class="dropdown-item text-success" href="client-status.php?id=<?php echo $client['id']; ?>&action=activate" onclick="return confirm('Are you sure you want to activate this client account?')">Activate Account</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="client-bookings.php?id=<?php echo $client['id']; ?>">View Bookings</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#clientsTable').DataTable({
            "pageLength": 20,
            "order": [[ 5, "desc" ]]
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
