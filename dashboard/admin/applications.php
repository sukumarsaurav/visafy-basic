<?php
$page_title = "Manage Applications";
$page_specific_css = "assets/css/applications.css";
require_once 'includes/header.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the base query for applications
$query = "SELECT a.*, 
          CONCAT(u.first_name, ' ', u.last_name) as applicant_name,
          vt.name as visa_type, 
          st.name as service_type,
          cm.name as consultation_mode,
          CONCAT(tm_u.first_name, ' ', tm_u.last_name) as team_member_name
          FROM visa_applications a
          LEFT JOIN users u ON a.user_id = u.id
          LEFT JOIN visa_service_configurations vsc ON a.service_config_id = vsc.id
          LEFT JOIN visa_types vt ON vsc.visa_type_id = vt.id
          LEFT JOIN service_types st ON vsc.service_type_id = st.id
          LEFT JOIN consultation_modes cm ON vsc.consultation_mode_id = cm.id
          LEFT JOIN team_members tm ON a.assigned_team_member_id = tm.id
          LEFT JOIN users tm_u ON tm.user_id = tm_u.id
          WHERE a.deleted_at IS NULL";

// Add filters
if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
}

if (!empty($date_from)) {
    $query .= " AND a.created_at >= ?";
}

if (!empty($date_to)) {
    $query .= " AND a.created_at <= ?";
}

if (!empty($search)) {
    $query .= " AND (a.reference_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR vt.name LIKE ?)";
}

$query .= " ORDER BY a.created_at DESC";

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
    $paramValues[] = $date_from . " 00:00:00";
}

if (!empty($date_to)) {
    $paramTypes .= 's';
    $paramValues[] = $date_to . " 23:59:59";
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
$applications = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="content">
    <div class="section-header">
        <h1>Applications Management</h1>
        <button id="create-application-btn" class="btn primary-btn">
            <i class="fas fa-plus"></i> Create New Application
        </button>
    </div>
    <p>Manage and track visa applications submitted by clients.</p>
    
    <!-- Filters Section -->
    <div class="section">
        <h2>Filter Applications</h2>
        <div class="filter-card">
            <form action="applications.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="in_review" <?php echo $status_filter === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                        <option value="document_requested" <?php echo $status_filter === 'document_requested' ? 'selected' : ''; ?>>Document Requested</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                    <a href="applications.php" class="btn reset-btn">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Applications Section -->
    <div class="section">
        <h2>Applications</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Applicant</th>
                        <th>Visa Type</th>
                        <th>Service</th>
                        <th>Mode</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Team Member</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($applications) > 0): ?>
                        <?php foreach ($applications as $application): ?>
                            <tr class="application-row status-<?php echo $application['status']; ?>">
                                <td><?php echo htmlspecialchars($application['reference_number']); ?></td>
                                <td><?php echo htmlspecialchars($application['applicant_name']); ?></td>
                                <td><?php echo htmlspecialchars($application['visa_type']); ?></td>
                                <td><?php echo htmlspecialchars($application['service_type']); ?></td>
                                <td><?php echo htmlspecialchars($application['consultation_mode']); ?></td>
                                <td>
                                    <?php if ($application['submitted_at']): ?>
                                        <?php echo date('M d, Y', strtotime($application['submitted_at'])); ?>
                                    <?php else: ?>
                                        <span class="badge warning">Not Submitted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $application['status']; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($application['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($application['team_member_name']): ?>
                                        <?php echo htmlspecialchars($application['team_member_name']); ?>
                                    <?php else: ?>
                                        <span class="badge warning">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button class="action-btn view-btn" onclick="location.href='view_application.php?id=<?php echo $application['id']; ?>'">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn assign-btn" data-id="<?php echo $application['id']; ?>"
                                        data-reference="<?php echo $application['reference_number']; ?>">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <button class="action-btn status-btn" data-id="<?php echo $application['id']; ?>"
                                        data-reference="<?php echo $application['reference_number']; ?>"
                                        data-status="<?php echo $application['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn documents-btn" onclick="location.href='application_documents.php?id=<?php echo $application['id']; ?>'">
                                        <i class="fas fa-file-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-data">No applications found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for creating a new application -->
<div class="modal" id="create-application-modal">
    <div class="modal-content large-modal">
        <span class="close">&times;</span>
        <h2>Create New Application</h2>
        
        <!-- Multi-step form -->
        <div class="multi-step-form">
            <!-- Step navigation -->
            <div class="step-navigation">
                <div class="step active" data-step="1">1. Select Client</div>
                <div class="step" data-step="2">2. Select Visa</div>
                <div class="step" data-step="3">3. Service Details</div>
                <div class="step" data-step="4">4. Team Assignment</div>
                <div class="step" data-step="5">5. Review</div>
            </div>
            
            <form id="create-application-form" action="process_application.php" method="POST">
                <!-- Step 1: Select Client -->
                <div class="form-step active" id="step-1">
                    <h3>Select Client</h3>
                    
                    <div class="client-selection">
                        <div class="form-group">
                            <label>Client Selection</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="client_option" value="existing" checked> Select Existing Client
                                </label>
                                <label>
                                    <input type="radio" name="client_option" value="new"> Create New Client
                                </label>
                            </div>
                        </div>
                        
                        <div id="existing-client-container">
                            <div class="form-group">
                                <label for="client_id">Select Client*</label>
                                <select name="client_id" id="client_id" class="form-control" required>
                                    <option value="">-- Select Client --</option>
                                    <?php
                                    // Fetch active clients
                                    $client_query = "SELECT id, CONCAT(first_name, ' ', last_name) as name, email FROM users 
                                                    WHERE user_type = 'applicant' AND status = 'active' AND deleted_at IS NULL
                                                    ORDER BY first_name, last_name";
                                    $client_result = $conn->query($client_query);
                                    while ($client = $client_result->fetch_assoc()) {
                                        echo "<option value='{$client['id']}'>{$client['name']} ({$client['email']})</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div id="new-client-container" style="display: none;">
                            <div class="form-group">
                                <label for="first_name">First Name*</label>
                                <input type="text" name="first_name" id="first_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name*</label>
                                <input type="text" name="last_name" id="last_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="email">Email*</label>
                                <input type="email" name="email" id="email" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn cancel-btn" id="cancel-application">Cancel</button>
                        <button type="button" class="btn next-btn" data-next="2">Next</button>
                    </div>
                </div>
                
                <!-- Step 2: Select Visa -->
                <div class="form-step" id="step-2">
                    <h3>Select Visa Type</h3>
                    
                    <div class="form-group">
                        <label for="country_id">Country*</label>
                        <select name="country_id" id="country_id" class="form-control" required>
                            <option value="">-- Select Country --</option>
                            <?php
                            // Fetch active countries
                            $country_query = "SELECT id, name FROM countries WHERE is_active = 1 ORDER BY name";
                            $country_result = $conn->query($country_query);
                            while ($country = $country_result->fetch_assoc()) {
                                echo "<option value='{$country['id']}'>{$country['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="visa_type_id">Visa Type*</label>
                        <select name="visa_type_id" id="visa_type_id" class="form-control" required disabled>
                            <option value="">-- Select Country First --</option>
                        </select>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn back-btn" data-back="1">Back</button>
                        <button type="button" class="btn next-btn" data-next="3">Next</button>
                    </div>
                </div>
                
                <!-- Step 3: Service Details -->
                <div class="form-step" id="step-3">
                    <h3>Service Details</h3>
                    
                    <div class="form-group">
                        <label for="service_type_id">Service Type*</label>
                        <select name="service_type_id" id="service_type_id" class="form-control" required>
                            <option value="">-- Select Service Type --</option>
                            <?php
                            // Fetch active service types
                            $service_query = "SELECT id, name, description FROM service_types WHERE is_active = 1 ORDER BY name";
                            $service_result = $conn->query($service_query);
                            while ($service = $service_result->fetch_assoc()) {
                                echo "<option value='{$service['id']}'>{$service['name']} - {$service['description']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="consultation_mode_id">Consultation Mode*</label>
                        <select name="consultation_mode_id" id="consultation_mode_id" class="form-control" required>
                            <option value="">-- Select Consultation Mode --</option>
                            <?php
                            // Fetch active consultation modes
                            $mode_query = "SELECT id, name, description FROM consultation_modes WHERE is_active = 1 ORDER BY name";
                            $mode_result = $conn->query($mode_query);
                            while ($mode = $mode_result->fetch_assoc()) {
                                echo "<option value='{$mode['id']}'>{$mode['name']} - {$mode['description']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="applicant_notes">Applicant Notes</label>
                        <textarea name="applicant_notes" id="applicant_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn back-btn" data-back="2">Back</button>
                        <button type="button" class="btn next-btn" data-next="4">Next</button>
                    </div>
                </div>
                
                <!-- Step 4: Team Assignment -->
                <div class="form-step" id="step-4">
                    <h3>Team Assignment</h3>
                    
                    <div class="form-group">
                        <label for="assigned_team_member_id">Assign to Team Member</label>
                        <select name="assigned_team_member_id" id="assigned_team_member_id" class="form-control">
                            <option value="">-- No Assignment --</option>
                            <?php
                            // Fetch active team members
                            $team_query = "SELECT tm.id, CONCAT(u.first_name, ' ', u.last_name) as name, tm.role 
                                           FROM team_members tm
                                           JOIN users u ON tm.user_id = u.id
                                           WHERE u.status = 'active' AND u.deleted_at IS NULL
                                           ORDER BY u.first_name, u.last_name";
                            $team_result = $conn->query($team_query);
                            while ($member = $team_result->fetch_assoc()) {
                                echo "<option value='{$member['id']}'>{$member['name']} ({$member['role']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn back-btn" data-back="3">Back</button>
                        <button type="button" class="btn next-btn" data-next="5">Next</button>
                    </div>
                </div>
                
                <!-- Step 5: Review -->
                <div class="form-step" id="step-5">
                    <h3>Review Application</h3>
                    
                    <div class="review-summary">
                        <h4>Application Summary</h4>
                        <div class="summary-group">
                            <div class="summary-item">
                                <span class="summary-label">Client:</span>
                                <span class="summary-value" id="summary-client">Not selected</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Country:</span>
                                <span class="summary-value" id="summary-country">Not selected</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Visa Type:</span>
                                <span class="summary-value" id="summary-visa">Not selected</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Service Type:</span>
                                <span class="summary-value" id="summary-service">Not selected</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Consultation Mode:</span>
                                <span class="summary-value" id="summary-mode">Not selected</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Team Member:</span>
                                <span class="summary-value" id="summary-team">Not assigned</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Initial Status*</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                        </select>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn back-btn" data-back="4">Back</button>
                        <button type="submit" name="create_application" class="btn submit-btn">Create Application</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal controls
const createModal = document.getElementById('create-application-modal');
const createBtn = document.getElementById('create-application-btn');
const closeBtn = document.querySelector('#create-application-modal .close');
const cancelBtn = document.getElementById('cancel-application');

// Open modal
createBtn.addEventListener('click', function() {
    createModal.style.display = 'block';
});

// Close modal
closeBtn.addEventListener('click', function() {
    createModal.style.display = 'none';
});

cancelBtn.addEventListener('click', function() {
    createModal.style.display = 'none';
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === createModal) {
        createModal.style.display = 'none';
    }
});

// Client selection toggle
const clientOptions = document.querySelectorAll('input[name="client_option"]');
const existingClientContainer = document.getElementById('existing-client-container');
const newClientContainer = document.getElementById('new-client-container');

clientOptions.forEach(option => {
    option.addEventListener('change', function() {
        if (this.value === 'existing') {
            existingClientContainer.style.display = 'block';
            newClientContainer.style.display = 'none';
            document.getElementById('client_id').setAttribute('required', true);
            document.getElementById('first_name').removeAttribute('required');
            document.getElementById('last_name').removeAttribute('required');
            document.getElementById('email').removeAttribute('required');
        } else {
            existingClientContainer.style.display = 'none';
            newClientContainer.style.display = 'block';
            document.getElementById('client_id').removeAttribute('required');
            document.getElementById('first_name').setAttribute('required', true);
            document.getElementById('last_name').setAttribute('required', true);
            document.getElementById('email').setAttribute('required', true);
        }
    });
});

// Visa type selection based on country
document.getElementById('country_id').addEventListener('change', function() {
    const countryId = this.value;
    const visaTypeSelect = document.getElementById('visa_type_id');
    
    visaTypeSelect.innerHTML = '<option value="">-- Loading Visa Types --</option>';
    visaTypeSelect.disabled = true;
    
    if (countryId) {
        // Fetch visa types for the selected country via AJAX
        fetch(`ajax/get_visa_types.php?country_id=${countryId}`)
            .then(response => response.json())
            .then(data => {
                visaTypeSelect.innerHTML = '<option value="">-- Select Visa Type --</option>';
                
                if (data.success && data.visa_types.length > 0) {
                    data.visa_types.forEach(visa => {
                        visaTypeSelect.innerHTML += `<option value="${visa.id}">${visa.name}</option>`;
                    });
                    visaTypeSelect.disabled = false;
                } else {
                    visaTypeSelect.innerHTML = '<option value="">No visa types available for this country</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching visa types:', error);
                visaTypeSelect.innerHTML = '<option value="">Error loading visa types</option>';
            });
    } else {
        visaTypeSelect.innerHTML = '<option value="">-- Select Country First --</option>';
    }
});

// Multi-step form navigation
const steps = document.querySelectorAll('.step');
const formSteps = document.querySelectorAll('.form-step');
const nextButtons = document.querySelectorAll('.next-btn');
const backButtons = document.querySelectorAll('.back-btn');

// Function to update the step display
function updateSteps(currentStep) {
    steps.forEach(step => {
        if (parseInt(step.dataset.step) === currentStep) {
            step.classList.add('active');
        } else {
            step.classList.remove('active');
        }
    });
    
    formSteps.forEach(formStep => {
        if (formStep.id === `step-${currentStep}`) {
            formStep.classList.add('active');
        } else {
            formStep.classList.remove('active');
        }
    });
}

// Handle next button clicks
nextButtons.forEach(button => {
    button.addEventListener('click', function() {
        const nextStep = parseInt(this.dataset.next);
        
        // Validate current step before proceeding
        const currentStep = nextStep - 1;
        const currentFormStep = document.getElementById(`step-${currentStep}`);
        const inputs = currentFormStep.querySelectorAll('input[required], select[required], textarea[required]');
        
        let isValid = true;
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }
        });
        
        if (isValid) {
            updateSteps(nextStep);
            
            // Update summary when moving to review step
            if (nextStep === 5) {
                updateSummary();
            }
        } else {
            alert('Please fill in all required fields before proceeding.');
        }
    });
});

// Handle back button clicks
backButtons.forEach(button => {
    button.addEventListener('click', function() {
        const prevStep = parseInt(this.dataset.back);
        updateSteps(prevStep);
    });
});

// Function to update summary
function updateSummary() {
    const clientOption = document.querySelector('input[name="client_option"]:checked').value;
    let clientText = 'Not selected';
    
    if (clientOption === 'existing') {
        const clientSelect = document.getElementById('client_id');
        if (clientSelect.selectedIndex > 0) {
            clientText = clientSelect.options[clientSelect.selectedIndex].text;
        }
    } else {
        const firstName = document.getElementById('first_name').value;
        const lastName = document.getElementById('last_name').value;
        const email = document.getElementById('email').value;
        
        if (firstName && lastName) {
            clientText = `${firstName} ${lastName} (${email}) - New Client`;
        }
    }
    
    const countrySelect = document.getElementById('country_id');
    const visaSelect = document.getElementById('visa_type_id');
    const serviceSelect = document.getElementById('service_type_id');
    const modeSelect = document.getElementById('consultation_mode_id');
    const teamSelect = document.getElementById('assigned_team_member_id');
    
    document.getElementById('summary-client').textContent = clientText;
    document.getElementById('summary-country').textContent = countrySelect.selectedIndex > 0 ? countrySelect.options[countrySelect.selectedIndex].text : 'Not selected';
    document.getElementById('summary-visa').textContent = visaSelect.selectedIndex > 0 ? visaSelect.options[visaSelect.selectedIndex].text : 'Not selected';
    document.getElementById('summary-service').textContent = serviceSelect.selectedIndex > 0 ? serviceSelect.options[serviceSelect.selectedIndex].text : 'Not selected';
    document.getElementById('summary-mode').textContent = modeSelect.selectedIndex > 0 ? modeSelect.options[modeSelect.selectedIndex].text : 'Not selected';
    document.getElementById('summary-team').textContent = teamSelect.selectedIndex > 0 ? teamSelect.options[teamSelect.selectedIndex].text : 'Not assigned';
}
</script>

<?php require_once 'includes/footer.php'; ?>
