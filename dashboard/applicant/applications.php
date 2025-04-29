<?php
$page_title = "My Applications";
require_once 'includes/header.php';

// Fetch all applications for the current user
$stmt = $conn->prepare("
    SELECT 
        va.*, 
        vt.name as visa_type_name,
        c.name as country_name,
        c.flag_image,
        st.name as service_type_name,
        cm.name as consultation_mode_name,
        (SELECT COUNT(*) FROM application_document_requests adr 
         WHERE adr.application_id = va.id AND adr.status = 'requested') as pending_documents
    FROM visa_applications va
    JOIN visa_types vt ON va.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id
    JOIN service_types st ON va.service_type_id = st.id
    JOIN consultation_modes cm ON va.consultation_mode_id = cm.id
    WHERE va.user_id = ? AND va.deleted_at IS NULL
    ORDER BY va.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<!-- Page Header -->
<div class="page-header">
    <h1>My Applications</h1>
    <div class="page-actions">
        <a href="new-application.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Application
        </a>
    </div>
</div>

<!-- Application Stats -->
<div class="application-stats">
    <?php
    // Get application statistics
    $stats_query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM visa_applications 
        WHERE user_id = ? AND deleted_at IS NULL";
    
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    ?>
    
    <div class="stat-card">
        <div class="stat-icon total">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Applications</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon draft">
            <i class="fas fa-pencil-alt"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['drafts']; ?></h3>
            <p>Draft</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon processing">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['processing']; ?></h3>
            <p>Processing</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon approved">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['approved']; ?></h3>
            <p>Approved</p>
        </div>
    </div>
</div>

<!-- Applications List -->
<div class="applications-list">
    <?php if ($applications->num_rows > 0): ?>
        <?php while ($app = $applications->fetch_assoc()): ?>
            <div class="application-card">
                <div class="application-header">
                    <div class="country-info">
                        <?php if ($app['flag_image']): ?>
                            <img src="../../assets/images/flags/<?php echo htmlspecialchars($app['flag_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($app['country_name']); ?> flag" 
                                 class="country-flag">
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($app['visa_type_name']); ?></h3>
                    </div>
                    <div class="application-status <?php echo $app['status']; ?>">
                        <?php echo ucfirst($app['status']); ?>
                    </div>
                </div>
                
                <div class="application-body">
                    <div class="application-info">
                        <p><strong>Reference:</strong> <?php echo htmlspecialchars($app['reference_number']); ?></p>
                        <p><strong>Service Type:</strong> <?php echo htmlspecialchars($app['service_type_name']); ?></p>
                        <p><strong>Consultation Mode:</strong> <?php echo htmlspecialchars($app['consultation_mode_name']); ?></p>
                        <p><strong>Submitted:</strong> 
                            <?php echo $app['submitted_at'] ? date('M d, Y', strtotime($app['submitted_at'])) : 'Not submitted'; ?>
                        </p>
                    </div>
                    
                    <?php if ($app['pending_documents'] > 0): ?>
                        <div class="document-alert">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $app['pending_documents']; ?> document(s) requested
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="application-footer">
                    <a href="view-application.php?id=<?php echo $app['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <?php if ($app['status'] === 'draft'): ?>
                        <a href="edit-application.php?id=<?php echo $app['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Continue Editing
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="no-applications">
            <i class="fas fa-file-alt"></i>
            <h2>No Applications Yet</h2>
            <p>Start your visa application process by clicking the button below</p>
            <a href="new-application.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Start New Application
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
$stmt->close();
$stats_stmt->close();
require_once 'includes/footer.php';
?>
