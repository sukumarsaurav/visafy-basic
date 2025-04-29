<?php
$page_title = "My Documents";
require_once 'includes/header.php';

// Fetch all document requests for the user's applications
$stmt = $conn->prepare("
    SELECT 
        adr.*,
        dt.name as document_type_name,
        dt.description as document_type_description,
        dc.name as category_name,
        va.reference_number as application_reference,
        va.status as application_status,
        vt.name as visa_type_name,
        c.name as country_name,
        c.flag_image,
        (
            SELECT ad.id 
            FROM application_documents ad 
            WHERE ad.request_id = adr.id 
            ORDER BY ad.uploaded_at DESC 
            LIMIT 1
        ) as latest_document_id,
        (
            SELECT ad.status 
            FROM application_documents ad 
            WHERE ad.request_id = adr.id 
            ORDER BY ad.uploaded_at DESC 
            LIMIT 1
        ) as document_status
    FROM application_document_requests adr
    JOIN document_types dt ON adr.document_type_id = dt.id
    JOIN document_categories dc ON dt.category_id = dc.id
    JOIN visa_applications va ON adr.application_id = va.id
    JOIN visa_types vt ON va.visa_type_id = vt.id
    JOIN countries c ON vt.country_id = c.id
    WHERE va.user_id = ?
    ORDER BY adr.due_date ASC, adr.requested_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$documents = $stmt->get_result();

// Get document statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT adr.id) as total_requests,
        SUM(CASE WHEN adr.status = 'requested' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN adr.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN adr.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN adr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(DISTINCT CASE WHEN adr.due_date < CURDATE() AND adr.status = 'requested' THEN adr.id END) as overdue
    FROM application_document_requests adr
    JOIN visa_applications va ON adr.application_id = va.id
    WHERE va.user_id = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!-- Page Header -->
<div class="page-header">
    <h1>My Documents</h1>
    <div class="page-actions">
        <button class="btn btn-secondary" id="uploadDocumentBtn">
            <i class="fas fa-upload"></i> Upload Document
        </button>
    </div>
</div>

<!-- Document Stats -->
<div class="document-stats">
    <div class="stat-card">
        <div class="stat-icon total">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['total_requests']; ?></h3>
            <p>Total Documents</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon pending">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>Pending Upload</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon submitted">
            <i class="fas fa-check"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['submitted']; ?></h3>
            <p>Submitted</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon overdue">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-details">
            <h3><?php echo $stats['overdue']; ?></h3>
            <p>Overdue</p>
        </div>
    </div>
</div>

<!-- Documents Container -->
<div class="documents-container">
    <!-- Filter Tabs -->
    <div class="document-filters">
        <button class="filter-btn active" data-filter="all">All Documents</button>
        <button class="filter-btn" data-filter="pending">Pending</button>
        <button class="filter-btn" data-filter="submitted">Submitted</button>
        <button class="filter-btn" data-filter="approved">Approved</button>
        <button class="filter-btn" data-filter="rejected">Rejected</button>
    </div>

    <?php if ($documents->num_rows > 0): ?>
        <div class="documents-grid">
            <?php while ($doc = $documents->fetch_assoc()): ?>
                <div class="document-card" data-status="<?php echo $doc['status']; ?>">
                    <div class="document-header">
                        <div class="document-type">
                            <i class="fas fa-file-alt"></i>
                            <h3><?php echo htmlspecialchars($doc['document_type_name']); ?></h3>
                        </div>
                        <div class="document-status <?php echo $doc['status']; ?>">
                            <?php echo ucfirst($doc['status']); ?>
                        </div>
                    </div>

                    <div class="document-body">
                        <div class="application-info">
                            <img src="../../assets/images/flags/<?php echo htmlspecialchars($doc['flag_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($doc['country_name']); ?>" 
                                 class="country-flag">
                            <div>
                                <p class="visa-type"><?php echo htmlspecialchars($doc['visa_type_name']); ?></p>
                                <p class="ref-number">Ref: <?php echo htmlspecialchars($doc['application_reference']); ?></p>
                            </div>
                        </div>

                        <div class="document-details">
                            <p class="category"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['category_name']); ?></p>
                            <?php if ($doc['description']): ?>
                                <p class="description"><?php echo htmlspecialchars($doc['document_type_description']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($doc['due_date']): ?>
                                <p class="due-date <?php echo strtotime($doc['due_date']) < time() ? 'overdue' : ''; ?>">
                                    <i class="fas fa-calendar"></i>
                                    Due: <?php echo date('M d, Y', strtotime($doc['due_date'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if ($doc['request_notes']): ?>
                            <div class="document-notes">
                                <i class="fas fa-info-circle"></i>
                                <?php echo htmlspecialchars($doc['request_notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="document-footer">
                        <?php if ($doc['status'] === 'requested'): ?>
                            <button class="btn btn-primary upload-btn" data-request-id="<?php echo $doc['id']; ?>">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                        <?php elseif ($doc['status'] === 'rejected'): ?>
                            <button class="btn btn-warning reupload-btn" data-request-id="<?php echo $doc['id']; ?>">
                                <i class="fas fa-redo"></i> Upload New Version
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary view-btn" data-document-id="<?php echo $doc['latest_document_id']; ?>">
                                <i class="fas fa-eye"></i> View Document
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-documents">
            <i class="fas fa-file-alt"></i>
            <h2>No Document Requests</h2>
            <p>You don't have any document requests at the moment.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Document Modal -->
<div class="modal" id="uploadModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Upload Document</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="documentUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="request_id" id="requestId">
                <div class="form-group">
                    <label for="documentFile">Select Document</label>
                    <input type="file" id="documentFile" name="document" required>
                    <p class="file-help">Accepted formats: PDF, JPG, PNG (Max size: 5MB)</p>
                </div>
                <div class="form-group">
                    <label for="notes">Additional Notes (Optional)</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary cancel-upload">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const filterButtons = document.querySelectorAll('.filter-btn');
    const documentCards = document.querySelectorAll('.document-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter;
            
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            documentCards.forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Modal functionality
    const modal = document.getElementById('uploadModal');
    const uploadBtns = document.querySelectorAll('.upload-btn, .reupload-btn');
    const closeModal = document.querySelector('.close-modal');
    const cancelUpload = document.querySelector('.cancel-upload');

    uploadBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('requestId').value = btn.dataset.requestId;
            modal.style.display = 'block';
        });
    });

    [closeModal, cancelUpload].forEach(elem => {
        elem.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    });

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Form submission
    const uploadForm = document.getElementById('documentUploadForm');
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        // Add your form submission logic here
    });
});
</script>

<?php
$stmt->close();
$stats_stmt->close();
require_once 'includes/footer.php';
?>
