<?php
$page_title = "Service Management";
$page_specific_css = "assets/css/services.css";
// $page_specific_js = "assets/js/services.js";
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
    <div class="section">
        <h2>Service Configuration</h2>
        <div class="header-actions">
            <button type="button" class="btn" id="add-service-btn">
                <i class="fas fa-plus"></i> Add New Service
            </button>
        </div>
        
        <!-- Service Configuration Table -->
        <div class="table-responsive">
            <table class="table" id="services-table">
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

<!-- Add/Edit Service Modal -->
<div class="modal" id="service-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Configure Service</h2>
        <form id="service-form">
            <input type="hidden" id="config-id">
            
            <div class="form-group">
                <label for="country">Country</label>
                <select class="form-select" id="country" name="country" required>
                    <option value="">Select Country</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo $country['id']; ?>">
                            <?php echo htmlspecialchars($country['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="visa-type">Visa Type</label>
                <select class="form-select" id="visa-type" name="visa_type_id" required disabled>
                    <option value="">Select Visa Type</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="service-type">Service Type</label>
                <select class="form-select" id="service-type" name="service_type_id" required>
                    <option value="">Select Service Type</option>
                    <?php foreach ($service_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>">
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="consultation-mode">Consultation Mode</label>
                <select class="form-select" id="consultation-mode" name="consultation_mode_id" required>
                    <option value="">Select Consultation Mode</option>
                    <?php foreach ($consultation_modes as $mode): ?>
                        <option value="<?php echo $mode['id']; ?>">
                            <?php echo htmlspecialchars($mode['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="price">Price</label>
                <div class="price-input">
                    <span class="currency-symbol">$</span>
                    <input type="number" id="price" name="price" 
                           required min="0" step="0.01">
                </div>
            </div>
            
            <div class="form-group">
                <label for="is-active">Status</label>
                <div class="toggle-switch">
                    <input type="checkbox" id="is-active" name="is_active" checked>
                    <label for="is-active"></label>
                </div>
                <small>Active services will be available for selection in bookings</small>
            </div>
            
            <div class="form-group">
                <button type="button" class="btn cancel-btn" id="cancel-service-btn">Cancel</button>
                <button type="button" class="btn" id="save-service-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<!-- DataTables JS will be loaded AFTER jQuery (in footer) -->

<?php require_once 'includes/footer.php'; ?>

<!-- Load DataTables after jQuery (which is in footer) -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
// Initialize after all resources are loaded
window.addEventListener('load', function() {
    console.log('Window loaded, jQuery version:', $.fn.jquery);
    console.log('DataTable available:', typeof $.fn.DataTable);
    
    // First, check if the visa_service_configurations table exists
    $.get('ajax/create_service_table.php')
        .done(function(response) {
            console.log('Table check:', response);
            initializeServicePage();
        })
        .fail(function(xhr, status, error) {
            console.error('Table check failed:', status, error);
            alert('Error initializing service page: ' + error);
        });
        
    function initializeServicePage() {
        // Get modal and button references
        const serviceModal = document.getElementById('service-modal');
        const addServiceBtn = document.getElementById('add-service-btn');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancel-service-btn');
        const saveServiceBtn = document.getElementById('save-service-btn');
        
        // Function to close modal
        function closeModal() {
            serviceModal.style.display = 'none';
        }
        
        // Initialize DataTable with error handling
        let servicesTable;
        try {
            servicesTable = $('#services-table').DataTable({
                processing: true,
                ajax: {
                    url: 'ajax/get_services.php',
                    dataSrc: function(json) {
                        console.log('DataTable data received:', json);
                        return json || [];
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTable AJAX error:', error, thrown);
                    }
                },
                columns: [
                    { data: 'country_name' },
                    { data: 'visa_type_name' },
                    { data: 'service_type_name' },
                    { data: 'consultation_mode_name' },
                    { 
                        data: 'price',
                        render: function(data) {
                            return `$${parseFloat(data).toFixed(2)}`;
                        }
                    },
                    {
                        data: 'is_active',
                        render: function(data) {
                            return data == 1 ? 
                                '<span class="status-badge active">Active</span>' : 
                                '<span class="status-badge inactive">Inactive</span>';
                        }
                    },
                    {
                        data: null,
                        render: function(data) {
                            return `
                                <button class="btn-small edit-btn" data-id="${data.id}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-small btn-danger delete-btn" data-id="${data.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                        }
                    }
                ],
                order: [[0, 'asc']],
                responsive: true
            });
            console.log('DataTable initialized successfully');
        } catch (e) {
            console.error('DataTable initialization error:', e.message);
            // Display error on the page
            $('#services-table').after('<div class="alert alert-danger">Error initializing table: ' + e.message + '</div>');
        }
        
        // Show modal when clicking "Add New Service" button
        addServiceBtn.addEventListener('click', function() {
            // Reset the form
            document.getElementById('service-form').reset();
            document.getElementById('config-id').value = '';
            document.getElementById('visa-type').disabled = true;
            
            // Display the modal
            serviceModal.style.display = 'block';
        });
        
        // Close modal when clicking X button
        closeBtn.addEventListener('click', closeModal);
        
        // Close modal when clicking Cancel button
        cancelBtn.addEventListener('click', closeModal);
        
        // Close modal when clicking outside of it
        window.addEventListener('click', function(event) {
            if (event.target === serviceModal) {
                closeModal();
            }
        });
        
        // Handle country change
        $('#country').change(function() {
            const countryId = $(this).val();
            const visaTypeSelect = $('#visa-type');
            
            visaTypeSelect.prop('disabled', true).empty()
                .append('<option value="">Select Visa Type</option>');
            
            if (countryId) {
                $.get('ajax/get_visa_type_services.php', { country_id: countryId })
                    .done(function(data) {
                        console.log('Received visa types:', data); // Debug output
                        
                        // Fix: Check if data is array or parse it if it's a JSON string
                        let visaTypes = data;
                        if (typeof data === 'string') {
                            try {
                                visaTypes = JSON.parse(data);
                            } catch (e) {
                                console.error('Failed to parse JSON:', e);
                            }
                        }
                        
                        // Handle both array and object formats
                        if (Array.isArray(visaTypes)) {
                            visaTypes.forEach(function(type) {
                                visaTypeSelect.append(
                                    `<option value="${type.id}">${type.name}</option>`
                                );
                            });
                            visaTypeSelect.prop('disabled', false);
                        } else if (visaTypes && typeof visaTypes === 'object') {
                            // If it's an object with keys like 0, 1, 2...
                            Object.values(visaTypes).forEach(function(type) {
                                visaTypeSelect.append(
                                    `<option value="${type.id}">${type.name}</option>`
                                );
                            });
                            visaTypeSelect.prop('disabled', false);
                        } else {
                            console.error('Unexpected response format:', visaTypes);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('AJAX request failed:', status, error);
                        alert('Failed to load visa types. Please try again.');
                    });
            }
        });
        
        // Handle edit button click
        $(document).on('click', '.edit-btn', function() {
            const id = $(this).data('id');
            
            $.get('ajax/get_service.php', { id: id })
                .done(function(data) {
                    $('#config-id').val(data.id);
                    $('#country').val(data.country_id).trigger('change');
                    
                    // Wait for visa types to load
                    setTimeout(() => {
                        $('#visa-type').val(data.visa_type_id);
                    }, 500);
                    
                    $('#service-type').val(data.service_type_id);
                    $('#consultation-mode').val(data.consultation_mode_id);
                    $('#price').val(data.price);
                    $('#is-active').prop('checked', data.is_active == 1);
                    
                    // Show the modal
                    serviceModal.style.display = 'block';
                });
        });
        
        // Handle save button click
        saveServiceBtn.addEventListener('click', function() {
            const form = document.getElementById('service-form');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const data = {
                id: document.getElementById('config-id').value,
                visa_type_id: document.getElementById('visa-type').value,
                service_type_id: document.getElementById('service-type').value,
                consultation_mode_id: document.getElementById('consultation-mode').value,
                price: document.getElementById('price').value,
                is_active: document.getElementById('is-active').checked ? 1 : 0
            };
            
            // Debug log data being sent
            console.log('Sending data:', data);
            
            $.post('ajax/save_service.php', data)
                .done(function(response) {
                    console.log('Save response:', response);
                    
                    // Parse the response if it's a string
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Failed to parse response:', e);
                        }
                    }
                    
                    if (response.success) {
                        closeModal();
                        
                        // Explicitly reload the DataTable
                        if (servicesTable) {
                            servicesTable.ajax.reload();
                            console.log('DataTable reloaded');
                        } else {
                            console.error('DataTable object not available for reload');
                            location.reload(); // Fallback to page reload
                        }
                        
                        alert(response.message || 'Service configuration saved successfully');
                    } else {
                        alert(response.message || 'Failed to save service configuration');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('AJAX save failed:', status, error);
                    alert('An error occurred while saving: ' + error);
                });
        });
        
        // Handle delete button click
        $(document).on('click', '.delete-btn', function() {
            const id = $(this).data('id');
            
            if (confirm('Are you sure you want to delete this service configuration?')) {
                $.post('ajax/delete_service.php', { id: id })
                    .done(function(response) {
                        // Parse the response if it's a string
                        if (typeof response === 'string') {
                            try {
                                response = JSON.parse(response);
                            } catch (e) {
                                console.error('Failed to parse response:', e);
                            }
                        }
                        
                        if (response.success) {
                            if (servicesTable) {
                                servicesTable.ajax.reload();
                            } else {
                                location.reload(); // Fallback
                            }
                            alert(response.message || 'Service configuration deleted successfully');
                        } else {
                            alert(response.message || 'Failed to delete service configuration');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('AJAX delete failed:', status, error);
                        alert('An error occurred while deleting: ' + error);
                    });
            }
        });
    }
});
</script>
