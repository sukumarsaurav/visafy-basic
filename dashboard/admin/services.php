<?php
$page_title = "Service Management";
$page_specific_css = "assets/css/services.css";
$page_specific_js = "assets/js/services.js";
require_once 'includes/header.php';
// require_once 'includes/admin_check.php';

// Fetch countries with active visa types
$stmt = $conn->prepare("
    SELECT DISTINCT c.* 
    FROM countries c 
    INNER JOIN visa_types v ON c.id = v.country_id 
    WHERE c.is_active = 1 AND v.is_active = 1
    ORDER BY c.name
");
$stmt->execute();
$countries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch service types
$stmt = $conn->prepare("SELECT * FROM service_types WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$service_types = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch consultation modes
$stmt = $conn->prepare("SELECT * FROM consultation_modes WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$consultation_modes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Service Configuration</h4>
                        <button type="button" class="btn btn-primary" id="add-service-btn">
                            <i class="fas fa-plus"></i> Add New Service
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Service Configuration Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="services-table">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th>Visa Type</th>
                                        <th>Service Type</th>
                                        <th>Consultation Mode</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data will be loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Service Modal -->
<div class="modal fade" id="service-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="service-form">
                    <input type="hidden" id="config-id">
                    
                    <div class="mb-3">
                        <label for="country" class="form-label">Country</label>
                        <select class="form-select" id="country" name="country" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country['id']; ?>">
                                    <?php echo htmlspecialchars($country['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="visa-type" class="form-label">Visa Type</label>
                        <select class="form-select" id="visa-type" name="visa_type_id" required disabled>
                            <option value="">Select Visa Type</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="service-type" class="form-label">Service Type</label>
                        <select class="form-select" id="service-type" name="service_type_id" required>
                            <option value="">Select Service Type</option>
                            <?php foreach ($service_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="consultation-mode" class="form-label">Consultation Mode</label>
                        <select class="form-select" id="consultation-mode" name="consultation_mode_id" required>
                            <option value="">Select Consultation Mode</option>
                            <?php foreach ($consultation_modes as $mode): ?>
                                <option value="<?php echo $mode['id']; ?>">
                                    <?php echo htmlspecialchars($mode['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="price" name="price" 
                                   required min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is-active" name="is_active" checked>
                            <label class="form-check-label" for="is-active">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-service-btn">Save</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
