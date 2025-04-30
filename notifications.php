<?php
$page_title = "Notifications | Visafy";
include('includes/functions.php');

// Check if user is logged in
if (!is_user_logged_in()) {
    // Redirect to login if not logged in
    redirect('login.php');
}

include('includes/header.php');
?>

<section class="notifications-section">
    <div class="container">
        <h1 class="page-title">Your Notifications</h1>
        
        <div class="notifications-container">
            <?php
            // Sample notifications - in a real application, these would come from the database
            $notifications = [
                [
                    'id' => 1,
                    'type' => 'info',
                    'message' => 'Welcome to Visafy! Your account has been created successfully.',
                    'date' => '2023-06-01 10:00:00',
                    'read' => true
                ],
                [
                    'id' => 2,
                    'type' => 'update',
                    'message' => 'Your profile has been updated successfully.',
                    'date' => '2023-06-05 14:30:00',
                    'read' => true
                ],
                [
                    'id' => 3,
                    'type' => 'alert',
                    'message' => 'Your eligibility check is ready to view.',
                    'date' => '2023-06-10 09:15:00',
                    'read' => false
                ]
            ];
            
            if (empty($notifications)) {
                echo '<div class="no-notifications">You don\'t have any notifications yet.</div>';
            } else {
                foreach ($notifications as $notification) {
                    $readClass = $notification['read'] ? 'notification-read' : 'notification-unread';
                    $typeClass = 'notification-' . $notification['type'];
                    $formattedDate = format_datetime($notification['date']);
                    
                    echo '<div class="notification-item ' . $readClass . ' ' . $typeClass . '">';
                    echo '<div class="notification-content">';
                    echo '<p class="notification-message">' . $notification['message'] . '</p>';
                    echo '<span class="notification-date">' . $formattedDate . '</span>';
                    echo '</div>';
                    echo '<div class="notification-actions">';
                    echo '<button class="mark-as-read" data-id="' . $notification['id'] . '"><i class="fas fa-check"></i></button>';
                    echo '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <div class="notification-controls">
            <button id="mark-all-read" class="btn btn-secondary">Mark All as Read</button>
        </div>
    </div>
</section>

<script src="/assets/js/notifications.js"></script>

<style>
    .notifications-section {
        padding: 60px 0;
    }
    
    .page-title {
        margin-bottom: 30px;
        color: #042167;
    }
    
    .notifications-container {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }
    
    .notification-item:last-child {
        border-bottom: none;
    }
    
    .notification-unread {
        background-color: rgba(4, 33, 103, 0.05);
    }
    
    .notification-read {
        opacity: 0.8;
    }
    
    .notification-message {
        margin: 0;
        font-size: 14px;
        color: #333;
    }
    
    .notification-date {
        display: block;
        font-size: 12px;
        color: #777;
        margin-top: 5px;
    }
    
    .notification-info {
        border-left: 4px solid #3498db;
    }
    
    .notification-update {
        border-left: 4px solid #2ecc71;
    }
    
    .notification-alert {
        border-left: 4px solid #e74c3c;
    }
    
    .notification-actions {
        display: flex;
        gap: 10px;
    }
    
    .mark-as-read {
        background: none;
        border: none;
        color: #777;
        cursor: pointer;
        font-size: 16px;
        transition: color 0.3s ease;
    }
    
    .mark-as-read:hover {
        color: #042167;
    }
    
    .notification-controls {
        text-align: right;
    }
    
    .no-notifications {
        padding: 30px;
        text-align: center;
        color: #777;
    }
</style>

<?php
include('includes/footer.php');
?> 