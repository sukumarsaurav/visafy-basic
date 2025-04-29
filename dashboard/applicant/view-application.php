<?php
// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is an applicant
if (!isLoggedIn() || $_SESSION['user_role'] !== 'applicant') {
    header("Location: ../../login.php");
    exit();
}

// Get application ID from URL
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate application ID
if ($application_id <= 0) {
    header("Location: applications.php");
    exit();
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Fetch application details
$stmt = $conn->prepare("
    SELECT a.*, vt.name as visa_type_name, vt.description as visa_type_description,
           vt.requirements as visa_type_requirements, vt.processing_time as visa_type_processing_time,
           vt.fees as visa_type_fees
    FROM applications a
    JOIN visa_types vt ON a.visa_type_id = vt.id
    WHERE a.id = ? AND a.user_id = ?
");
$stmt->bind_param("ii", $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: applications.php");
    exit();
}

$application = $result->fetch_assoc();

// Fetch application documents
$stmt = $conn->prepare("
    SELECT ad.*, dt.name as document_type_name, dt.description as document_type_description
    FROM application_documents ad
    JOIN document_types dt ON ad.document_type_id = dt.id
    WHERE ad.application_id = ?
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch application status history
$stmt = $conn->prepare("
    SELECT ash.*, u.first_name, u.last_name, u.email
    FROM application_status_history ash
    LEFT JOIN users u ON ash.updated_by = u.id
    WHERE ash.application_id = ?
    ORDER BY ash.created_at DESC
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$status_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get notifications
$notifications = getNotifications($user_id);
$unread_notifications = array_filter($notifications, function($notification) {
    return $notification['is_read'] == 0;
});
$unread_count = count($unread_notifications);

// Set page title
$page_title = "Application Details - " . $application['visa_type_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    
    <style>
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }
        .status-draft { background-color: #6c757d; color: white; }
        .status-submitted { background-color: #0d6efd; color: white; }
        .status-under-review { background-color: #ffc107; color: black; }
        .status-additional-docs { background-color: #fd7e14; color: white; }
        .status-approved { background-color: #198754; color: white; }
        .status-rejected { background-color: #dc3545; color: white; }
        
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 15px;
            height: 100%;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding-left: 30px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                    <h1 class="h2">Application Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($application['status'] === 'draft'): ?>
                            <a href="edit-application.php?id=<?php echo $application_id; ?>" class="btn btn-primary me-2">
                                <i class="fas fa-edit"></i> Edit Application
                            </a>
                        <?php endif; ?>
                        <a href="applications.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Applications
                        </a>
                    </div>
                </div>
                
                <!-- Application Overview -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Application Overview</h5>
                                <table class="table">
                                    <tr>
                                        <th>Application ID:</th>
                                        <td>#<?php echo str_pad($application['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Visa Type:</th>
                                        <td><?php echo htmlspecialchars($application['visa_type_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $application['status'])); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $application['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Submission Date:</th>
                                        <td><?php echo date('F j, Y', strtotime($application['submission_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Updated:</th>
                                        <td><?php echo date('F j, Y H:i', strtotime($application['updated_at'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Visa Information</h5>
                                <p><?php echo nl2br(htmlspecialchars($application['visa_type_description'])); ?></p>
                                
                                <h6 class="mt-3">Requirements:</h6>
                                <ul>
                                    <?php foreach (json_decode($application['visa_type_requirements'], true) as $requirement): ?>
                                        <li><?php echo htmlspecialchars($requirement); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <h6 class="mt-3">Processing Time:</h6>
                                <p><?php echo htmlspecialchars($application['visa_type_processing_time']); ?></p>
                                
                                <h6 class="mt-3">Fees:</h6>
                                <p><?php echo htmlspecialchars($application['visa_type_fees']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Documents -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Documents</h5>
                        <?php if (empty($documents)): ?>
                            <p class="text-muted">No documents uploaded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Document Type</th>
                                            <th>Description</th>
                                            <th>File</th>
                                            <th>Upload Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $document): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($document['document_type_name']); ?></td>
                                                <td><?php echo htmlspecialchars($document['document_type_description']); ?></td>
                                                <td>
                                                    <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </td>
                                                <td><?php echo date('F j, Y', strtotime($document['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $document['status'] === 'approved' ? 'success' : ($document['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                        <?php echo ucfirst($document['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Status History -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Status History</h5>
                        <div class="timeline">
                            <?php foreach ($status_history as $history): ?>
                                <div class="timeline-item">
                                    <h6><?php echo ucwords(str_replace('_', ' ', $history['status'])); ?></h6>
                                    <p class="text-muted mb-1">
                                        <?php echo date('F j, Y H:i', strtotime($history['created_at'])); ?>
                                        <?php if ($history['updated_by']): ?>
                                            by <?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($history['comments']): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($history['comments'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../assets/js/script.js"></script>
</body>
</html> 