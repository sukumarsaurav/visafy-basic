<?php
$page_title = "My Bookings";
require_once 'includes/header.php';

// Fetch all bookings for the current user
$stmt = $conn->prepare("
    SELECT 
        b.*,
        vt.name as visa_type_name,
        c.name as country_name,
        c.flag_image,
        st.name as service_type_name,
        cm.name as consultation_mode_name,
        CONCAT(u.first_name, ' ', u.last_name) as team_member_name,
        u.profile_picture as team_member_picture
    FROM bookings b
    JOIN visa_types vt ON b.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id
    JOIN service_types st ON b.service_type_id = st.id
    JOIN consultation_modes cm ON b.consultation_mode_id = cm.id
    JOIN team_members tm ON b.team_member_id = tm.id
    JOIN users u ON tm.user_id = u.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC, b.start_time DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result();

// Get booking statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN booking_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming
    FROM bookings 
    WHERE user_id = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!-- Page Header -->
<div class="page-header">
    <h1>My Bookings</h1>
    <div class="page-actions">
        <a href="new-booking.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Schedule Consultation
        </a>
    </div>
</div>

<!-- Booking Stats -->
<div class="booking-stats">
    <div class="stat-card">
        <div class="stat-icon total">
            <i class="fas fa-calendar"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Bookings</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon upcoming">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['upcoming']; ?></h3>
            <p>Upcoming</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon confirmed">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['confirmed']; ?></h3>
            <p>Confirmed</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon completed">
            <i class="fas fa-flag-checkered"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['completed']; ?></h3>
            <p>Completed</p>
        </div>
    </div>
</div>

<!-- Bookings List -->
<div class="bookings-container">
    <?php if ($bookings->num_rows > 0): ?>
        <!-- Filter Tabs -->
        <div class="booking-filters">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="upcoming">Upcoming</button>
            <button class="filter-btn" data-filter="completed">Completed</button>
            <button class="filter-btn" data-filter="cancelled">Cancelled</button>
        </div>

        <div class="bookings-list">
            <?php while ($booking = $bookings->fetch_assoc()): ?>
                <?php 
                $is_upcoming = strtotime($booking['booking_date']) >= strtotime('today');
                $booking_class = $is_upcoming ? 'upcoming' : 'past';
                ?>
                <div class="booking-card <?php echo $booking_class; ?>" data-status="<?php echo $booking['status']; ?>">
                    <div class="booking-header">
                        <div class="booking-date">
                            <div class="date-badge">
                                <span class="month"><?php echo date('M', strtotime($booking['booking_date'])); ?></span>
                                <span class="day"><?php echo date('d', strtotime($booking['booking_date'])); ?></span>
                                <span class="year"><?php echo date('Y', strtotime($booking['booking_date'])); ?></span>
                            </div>
                            <div class="time">
                                <?php echo date('h:i A', strtotime($booking['start_time'])); ?> - 
                                <?php echo date('h:i A', strtotime($booking['end_time'])); ?>
                            </div>
                        </div>
                        <div class="booking-status <?php echo $booking['status']; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </div>
                    </div>

                    <div class="booking-body">
                        <div class="consultation-details">
                            <h3><?php echo htmlspecialchars($booking['visa_type_name']); ?></h3>
                            <p class="consultation-type">
                                <i class="fas fa-handshake"></i>
                                <?php echo htmlspecialchars($booking['service_type_name']); ?> - 
                                <?php echo htmlspecialchars($booking['consultation_mode_name']); ?>
                            </p>
                            <p class="reference">
                                <i class="fas fa-hashtag"></i>
                                Ref: <?php echo htmlspecialchars($booking['reference_number']); ?>
                            </p>
                        </div>

                        <div class="consultant-info">
                            <img src="<?php echo !empty($booking['team_member_picture']) ? 
                                '../../uploads/profile/' . $booking['team_member_picture'] : 
                                '../../assets/images/default-profile.jpg'; ?>" 
                                alt="Consultant" class="consultant-avatar">
                            <div class="consultant-details">
                                <h4><?php echo htmlspecialchars($booking['team_member_name']); ?></h4>
                                <p>Your Consultant</p>
                            </div>
                        </div>
                    </div>

                    <div class="booking-footer">
                        <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($booking['status'] === 'pending' || $booking['status'] === 'confirmed'): ?>
                            <?php if ($is_upcoming): ?>
                                <button class="btn btn-warning reschedule-btn" data-booking-id="<?php echo $booking['id']; ?>">
                                    <i class="fas fa-clock"></i> Reschedule
                                </button>
                                <button class="btn btn-danger cancel-btn" data-booking-id="<?php echo $booking['id']; ?>">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-bookings">
            <i class="fas fa-calendar-alt"></i>
            <h2>No Bookings Yet</h2>
            <p>Schedule your first consultation by clicking the button below</p>
            <a href="new-booking.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Schedule Consultation
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const filterButtons = document.querySelectorAll('.filter-btn');
    const bookingCards = document.querySelectorAll('.booking-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter;
            
            // Update active button
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            // Filter booking cards
            bookingCards.forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php
$stmt->close();
$stats_stmt->close();
require_once 'includes/footer.php';
?>
