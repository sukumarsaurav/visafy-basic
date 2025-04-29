<?php
// Start session only if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is a member
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'member') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION["id"];

// Fetch user data and team member role
$stmt = $conn->prepare("SELECT u.*, tm.role, tm.permissions FROM users u 
                        LEFT JOIN team_members tm ON u.id = tm.user_id 
                        WHERE u.id = ? AND u.user_type = 'member'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: ../../login.php");
    exit();
}

$user = $result->fetch_assoc();
$member_role = $user['role'];
$permissions = json_decode($user['permissions'] ?? '{}', true);
$stmt->close();

// Check for unread notifications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_result = $stmt->get_result();
$notification_count = $notif_result->fetch_assoc()['count'];
$stmt->close();

// Get recent notifications (limit to 5)
$stmt = $conn->prepare("SELECT id, title, content, is_read, created_at FROM notifications 
                       WHERE user_id = ? AND is_read = 0 
                       ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$notifications_list = [];
while ($notification = $notifications->fetch_assoc()) {
    $notifications_list[] = $notification;
}
$stmt->close();

// Debug: If there are no notifications but we have a count, something's wrong
if (empty($notifications_list) && $notification_count > 0) {
    error_log("Warning: Notifications count is $notification_count but no notifications were fetched.");
}

// Determine if sidebar should be collapsed based on user preference or default
$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';
$sidebar_class = $sidebar_collapsed ? 'collapsed' : '';
$main_content_class = $sidebar_collapsed ? 'expanded' : '';

// Prepare profile image
$profile_img = '../../assets/images/default-profile.jpg';
// Check for profile image
$profile_image = !empty($user['profile_picture']) ? $user['profile_picture'] : '';

if (!empty($profile_image)) {
    // Check if file exists
    if (file_exists('../../uploads/profiles/' . $profile_image)) {
        $profile_img = '../../uploads/profiles/' . $profile_image;
    } else if (file_exists('../../uploads/profile/' . $profile_image)) {
        $profile_img = '../../uploads/profile/' . $profile_image;
    }
}

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Define menu items based on member role
$menu_items = [
    'dashboard' => [
        'icon' => 'fas fa-tachometer-alt',
        'text' => 'Dashboard',
        'url' => 'index.php',
        'roles' => ['all']
    ],
    'tasks' => [
        'icon' => 'fas fa-tasks',
        'text' => 'Tasks',
        'url' => 'tasks.php',
        'roles' => ['Case Manager', 'Document Creator', 'Immigration Assistant', 'Career Consultant', 'Business Plan Creator']
    ],
    'applications' => [
        'icon' => 'fas fa-file-alt',
        'text' => 'Applications',
        'url' => 'applications.php',
        'roles' => ['Case Manager', 'Document Creator', 'Immigration Assistant']
    ],
    'clients' => [
        'icon' => 'fas fa-users',
        'text' => 'Clients',
        'url' => 'clients.php',
        'roles' => ['Case Manager', 'Immigration Assistant', 'Career Consultant', 'Business Plan Creator']
    ],
    'bookings' => [
        'icon' => 'fas fa-calendar-check',
        'text' => 'Bookings',
        'url' => 'bookings.php',
        'roles' => ['Case Manager', 'Immigration Assistant', 'Career Consultant', 'Business Plan Creator']
    ],
    'documents' => [
        'icon' => 'fas fa-file-pdf',
        'text' => 'Documents',
        'url' => 'documents.php',
        'roles' => ['Document Creator', 'Case Manager', 'Immigration Assistant']
    ],
    'messages' => [
        'icon' => 'fas fa-envelope',
        'text' => 'Messages',
        'url' => 'messages.php',
        'roles' => ['all']
    ],
    'profile' => [
        'icon' => 'fas fa-user',
        'text' => 'Profile',
        'url' => 'profile.php',
        'roles' => ['all']
    ],
    'settings' => [
        'icon' => 'fas fa-cog',
        'text' => 'Settings',
        'url' => 'settings.php',
        'roles' => ['all']
    ]
];

// Function to check if a menu item should be shown based on role
function shouldShowMenuItem($item, $member_role) {
    if (in_array('all', $item['roles'])) {
        return true;
    }
    return in_array($member_role, $item['roles']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Member Dashboard'; ?> - Visafy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/member.css">
</head>

<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button id="sidebar-toggle" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="header-logo">
                    <img src="../../assets/images/logo-Visafy-light.png" alt="Visafy Logo" class="desktop-logo">
                </a>
            </div>
            <div class="header-right">
                <div class="notification-dropdown">
                    <div class="notification-icon" id="notification-toggle">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="notification-content" id="notification-content">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <?php if ($notification_count > 0): ?>
                            <a href="notifications.php" class="mark-all-read">Mark all as read</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($notifications_list)): ?>
                            <div class="notification-item">
                                <p>No new notifications</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications_list as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                <div class="notification-icon-small">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="notification-details">
                                    <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($notification['content']); ?></p>
                                    <span class="notification-time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-footer">
                            <a href="notifications.php">View all notifications</a>
                        </div>
                    </div>
                </div>
                <div class="user-dropdown">
                    <div class="user-info" id="user-dropdown-toggle">
                        <img src="<?php echo $profile_img; ?>" alt="Profile" class="user-avatar">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="user-dropdown-content" id="user-dropdown-content">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../../logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <aside class="sidebar <?php echo $sidebar_class; ?>">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <img src="../../assets/images/logo-Visafy-dark.png" alt="Visafy Logo">
                </a>
            </div>
            <div class="user-profile">
                <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-img">
                <div class="profile-info">
                    <h3 class="profile-name"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></h3>
                    <span class="role-badge"><?php echo htmlspecialchars($member_role); ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <?php foreach ($menu_items as $key => $item): ?>
                        <?php if (shouldShowMenuItem($item, $member_role)): ?>
                        <li class="<?php echo $current_page == $key ? 'active' : ''; ?>">
                            <a href="<?php echo $item['url']; ?>">
                                <i class="<?php echo $item['icon']; ?>"></i>
                                <span><?php echo $item['text']; ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content <?php echo $main_content_class; ?>">
            <div class="content-wrapper">
                <!-- Page content will be inserted here -->
            </div>
        </main>
    </div>
</body>
</html>
