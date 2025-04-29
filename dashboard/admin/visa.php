<?php
$page_title = "Visa Management";
// $page_specific_js = "assets/js/visa.js"; // Set the page-specific JS file
require_once 'includes/header.php';
?>

<div class="content">
    <h1>Visa Management</h1>
    <p>Manage countries, visa types, and required documents for visa applications.</p>

    <!-- Country Cards Section -->
    <div class="section">
        <h2>Countries</h2>
        <div class="cards-container" id="countries-container">
            <!-- Add Country Card -->
            <div class="card add-card" id="add-country-card">
                <div class="card-content">
                    <i class="fas fa-plus"></i>
                    <p>Add Country</p>
                </div>
            </div>
            
            <?php
            // Fetch countries from the database
            $stmt = $conn->prepare("SELECT id, name, code, flag_image, is_active FROM countries ORDER BY name");
            $stmt->execute();
            $countries = $stmt->get_result();
            
            while ($country = $countries->fetch_assoc()) {
                $flag_img = !empty($country['flag_image']) ? "../../uploads/flags/" . $country['flag_image'] : "../../assets/images/default-flag.png";
                $status_class = $country['is_active'] ? 'active' : 'inactive';
                
                echo "<div class='card country-card' data-id='{$country['id']}' data-name='" . htmlspecialchars($country['name']) . "'>";
                echo "<div class='card-content'>";
                echo "<div class='flag-container'><img src='{$flag_img}' alt='{$country['name']} flag' class='flag-image'></div>";
                echo "<h3>" . htmlspecialchars($country['name']) . "</h3>";
                echo "<p class='country-code'>Code: {$country['code']}</p>";
                echo "<span class='status-indicator {$status_class}'></span>";
                echo "</div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- Visa Types Section (Initially Hidden) -->
    <div class="section" id="visa-types-section" style="display:none;">
        <h2>Visa Types for <span id="selected-country-name"></span></h2>
        <div class="cards-container" id="visa-types-container">
            <!-- Add Visa Type Card will be added here -->
        </div>
    </div>

    <!-- Required Documents Section (Initially Hidden) -->
    <div class="section" id="documents-section" style="display:none;">
        <h2>Required Documents for <span id="selected-visa-type-name"></span></h2>
        <div class="documents-container" id="documents-container">
            <!-- Documents list will be added here -->
        </div>
    </div>
</div>

<!-- Modal for adding a country -->
<div class="modal" id="add-country-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Add New Country</h2>
        <form id="add-country-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="country-name">Country Name*</label>
                <input type="text" id="country-name" name="country_name" required placeholder="Enter country name">
            </div>

            <div class="form-group">
                <label for="country-code">Country Code*</label>
                <input type="text" id="country-code" name="country_code" required placeholder="Two-letter ISO code (e.g., US)" maxlength="2">
                <small>Two-letter ISO country code (e.g., US for United States)</small>
            </div>

            <div class="form-group">
                <label for="country-flag">Flag Image</label>
                <input type="file" id="country-flag" name="country_flag" accept="image/png, image/jpeg">
                <small>Recommended size: 32x24 pixels, PNG format</small>
            </div>

            <div class="form-group">
                <label for="country-active">Status</label>
                <div class="toggle-switch">
                    <input type="checkbox" id="country-active" name="is_active" checked>
                    <label for="country-active"></label>
                </div>
                <small>Active countries will be available for selection in visa applications</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">Add Country</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for adding a visa type -->
<div class="modal" id="add-visa-type-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Add New Visa Type</h2>
        <form id="add-visa-type-form">
            <input type="hidden" id="visa-country-id" name="country_id">
            
            <div class="form-group">
                <label for="visa-type-name">Visa Type Name*</label>
                <input type="text" id="visa-type-name" name="visa_type_name" required placeholder="Enter visa type name">
            </div>

            <div class="form-group">
                <label for="visa-type-code">Visa Code</label>
                <input type="text" id="visa-type-code" name="visa_code" placeholder="Visa code (optional)">
            </div>

            <div class="form-group">
                <label for="visa-type-description">Description</label>
                <textarea id="visa-type-description" name="description" placeholder="Brief description of this visa type"></textarea>
            </div>

            <div class="form-group">
                <label for="visa-processing-time">Processing Time</label>
                <input type="text" id="visa-processing-time" name="processing_time" placeholder="e.g., 2-4 weeks">
            </div>

            <div class="form-group">
                <label for="visa-validity">Validity Period</label>
                <input type="text" id="visa-validity" name="validity_period" placeholder="e.g., 6 months">
            </div>

            <div class="form-group">
                <label for="visa-active">Status</label>
                <div class="toggle-switch">
                    <input type="checkbox" id="visa-active" name="is_active" checked>
                    <label for="visa-active"></label>
                </div>
            </div>

            <h3>Required Documents</h3>
            <div id="document-categories">
                <?php
                // Fetch document categories
                $stmt = $conn->prepare("SELECT id, name FROM document_categories ORDER BY name");
                $stmt->execute();
                $categories = $stmt->get_result();

                while ($category = $categories->fetch_assoc()) {
                    echo "<div class='document-category'>";
                    echo "<h4>" . htmlspecialchars($category['name']) . "</h4>";
                    
                    // Fetch documents for this category
                    $doc_stmt = $conn->prepare("SELECT id, name FROM document_types WHERE category_id = ? ORDER BY name");
                    $doc_stmt->bind_param("i", $category['id']);
                    $doc_stmt->execute();
                    $documents = $doc_stmt->get_result();
                    
                    echo "<div class='document-checkboxes'>";
                    while ($doc = $documents->fetch_assoc()) {
                        echo "<div class='checkbox-wrapper'>";
                        echo "<input type='checkbox' id='doc-{$doc['id']}' name='documents[]' value='{$doc['id']}'>";
                        echo "<label for='doc-{$doc['id']}'>" . htmlspecialchars($doc['name']) . "</label>";
                        echo "<div class='doc-mandatory'>";
                        echo "<input type='checkbox' id='mandatory-{$doc['id']}' name='mandatory[{$doc['id']}]' value='1' checked>";
                        echo "<label for='mandatory-{$doc['id']}'>Mandatory</label>";
                        echo "</div>";
                        echo "</div>";
                    }
                    echo "</div>";
                    echo "</div>";
                }
                ?>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">Add Visa Type</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for adding a document -->
<div class="modal" id="add-document-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Add New Document</h2>
        <form id="add-document-form">
            <input type="hidden" id="doc-visa-type-id" name="visa_type_id">
            
            <div class="form-group">
                <label for="document-category">Document Category*</label>
                <select id="document-category" name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php
                    // Reset the result pointer
                    $categories->data_seek(0);
                    while ($category = $categories->fetch_assoc()) {
                        echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                    }
                    ?>
                    <option value="new">+ Add New Category</option>
                </select>
            </div>

            <div class="form-group" id="new-category-group" style="display:none;">
                <label for="new-category-name">New Category Name*</label>
                <input type="text" id="new-category-name" name="new_category_name" placeholder="Enter new category name">
            </div>

            <div class="form-group">
                <label for="document-name">Document Name*</label>
                <input type="text" id="document-name" name="document_name" required placeholder="Enter document name">
            </div>

            <div class="form-group">
                <label for="document-description">Description</label>
                <textarea id="document-description" name="description" placeholder="Brief description of this document"></textarea>
            </div>

            <div class="form-group">
                <label for="document-mandatory">Is Mandatory</label>
                <div class="toggle-switch">
                    <input type="checkbox" id="document-mandatory" name="is_mandatory" checked>
                    <label for="document-mandatory"></label>
                </div>
            </div>

            <div class="form-group">
                <label for="additional-requirements">Additional Requirements</label>
                <textarea id="additional-requirements" name="additional_requirements" placeholder="Specific formatting or requirements for this document"></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">Add Document</button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize variables
let selectedCountryId = null;
let selectedCountryName = null;
let selectedVisaTypeId = null;
let selectedVisaTypeName = null;

// Modal functionality
const modals = {
    country: document.getElementById('add-country-modal'),
    visaType: document.getElementById('add-visa-type-modal'),
    document: document.getElementById('add-document-modal')
};

// Get all close buttons and add event listeners
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    for (const modalKey in modals) {
        if (event.target === modals[modalKey]) {
            modals[modalKey].style.display = 'none';
        }
    }
});

// Open add country modal
document.getElementById('add-country-card').addEventListener('click', function() {
    modals.country.style.display = 'block';
});

// Handle country form submission
document.getElementById('add-country-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax/add_country.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Country added successfully!');
            location.reload(); // Reload to update the country list
        } else {
            alert('Error adding country: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
});

// Country card click event
document.addEventListener('click', function(e) {
    const countryCard = e.target.closest('.country-card');
    if (countryCard) {
        // Deselect all country cards
        document.querySelectorAll('.country-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Select this card
        countryCard.classList.add('selected');
        
        // Update selected country info
        selectedCountryId = countryCard.dataset.id;
        selectedCountryName = countryCard.dataset.name;
        
        // Update UI
        document.getElementById('selected-country-name').textContent = selectedCountryName;
        document.getElementById('visa-types-section').style.display = 'block';
        document.getElementById('documents-section').style.display = 'none';
        
        // Load visa types for this country
        loadVisaTypes(selectedCountryId);
    }
});

// Load visa types for a country
function loadVisaTypes(countryId) {
    const container = document.getElementById('visa-types-container');
    container.innerHTML = '<div class="loading">Loading visa types...</div>';
    
    fetch('ajax/get_visa_types.php?country_id=' + countryId)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            // Add the "Add Visa Type" card first
            const addCard = document.createElement('div');
            addCard.className = 'card add-card';
            addCard.id = 'add-visa-type-card';
            addCard.innerHTML = `
                <div class="card-content">
                    <i class="fas fa-plus"></i>
                    <p>Add Visa Type</p>
                </div>
            `;
            container.appendChild(addCard);
            
            // We'll handle this event via event delegation instead of direct binding
            
            if (data.success && data.visa_types.length > 0) {
                data.visa_types.forEach(visa => {
                    const visaCard = document.createElement('div');
                    visaCard.className = 'card visa-card';
                    visaCard.dataset.id = visa.id;
                    visaCard.dataset.name = visa.name;
                    
                    let processingTime = visa.processing_time ? `<p>Processing: ${visa.processing_time}</p>` : '';
                    let validityPeriod = visa.validity_period ? `<p>Validity: ${visa.validity_period}</p>` : '';
                    
                    visaCard.innerHTML = `
                        <div class="card-content">
                            <h3>${visa.name}</h3>
                            <p class="visa-code">Code: ${visa.code || 'N/A'}</p>
                            ${processingTime}
                            ${validityPeriod}
                        </div>
                    `;
                    container.appendChild(visaCard);
                });
            } else {
                container.innerHTML += '<div class="no-items">No visa types found for this country.</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="error">Error loading visa types: ' + error + '</div>';
        });
}

// Add this event listener for the "Add Visa Type" card using event delegation
document.addEventListener('click', function(e) {
    const addVisaTypeCard = e.target.closest('#add-visa-type-card');
    if (addVisaTypeCard) {
        document.getElementById('visa-country-id').value = selectedCountryId;
        modals.visaType.style.display = 'block';
    }
});

// Visa Type card click event (delegated)
document.addEventListener('click', function(e) {
    const visaCard = e.target.closest('.visa-card');
    if (visaCard) {
        // Deselect all visa cards
        document.querySelectorAll('.visa-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Select this card
        visaCard.classList.add('selected');
        
        // Update selected visa type info
        selectedVisaTypeId = visaCard.dataset.id;
        selectedVisaTypeName = visaCard.dataset.name;
        
        // Update UI
        document.getElementById('selected-visa-type-name').textContent = selectedVisaTypeName;
        document.getElementById('documents-section').style.display = 'block';
        
        // Load documents for this visa type
        loadDocuments(selectedVisaTypeId);
    }
});

// Load documents for a visa type
function loadDocuments(visaTypeId) {
    const container = document.getElementById('documents-container');
    container.innerHTML = '<div class="loading">Loading required documents...</div>';
    
    fetch('ajax/get_required_documents.php?visa_type_id=' + visaTypeId)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            // Add "Add Document" button
            const addButton = document.createElement('button');
            addButton.className = 'btn add-document-btn';
            addButton.innerText = 'Add Document';
            addButton.addEventListener('click', function() {
                document.getElementById('doc-visa-type-id').value = selectedVisaTypeId;
                modals.document.style.display = 'block';
            });
            container.appendChild(addButton);
            
            // Create documents table
            const table = document.createElement('table');
            table.className = 'documents-table';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Document Name</th>
                        <th>Mandatory</th>
                        <th>Requirements</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="documents-tbody"></tbody>
            `;
            container.appendChild(table);
            
            const tbody = document.getElementById('documents-tbody');
            
            if (data.success && data.documents.length > 0) {
                data.documents.forEach(doc => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${doc.name}</td>
                        <td>${doc.is_mandatory ? 'Yes' : 'No'}</td>
                        <td>${doc.additional_requirements || '-'}</td>
                        <td>
                            <button class="btn-small edit-doc" data-id="${doc.id}">Edit</button>
                            <button class="btn-small btn-danger delete-doc" data-id="${doc.id}">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="4" class="no-items">No required documents found for this visa type.</td></tr>`;
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="error">Error loading documents: ' + error + '</div>';
        });
}

// Handle visa type form submission
document.getElementById('add-visa-type-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax/add_visa_type.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Visa type added successfully!');
            modals.visaType.style.display = 'none';
            loadVisaTypes(selectedCountryId);
        } else {
            alert('Error adding visa type: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
});

// Handle document form submission
document.getElementById('add-document-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('ajax/add_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Document added successfully!');
            modals.document.style.display = 'none';
            loadDocuments(selectedVisaTypeId);
        } else {
            alert('Error adding document: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
});

// Show/hide new category input based on selection
document.getElementById('document-category').addEventListener('change', function() {
    const newCategoryGroup = document.getElementById('new-category-group');
    newCategoryGroup.style.display = this.value === 'new' ? 'block' : 'none';
    
    if (this.value === 'new') {
        document.getElementById('new-category-name').setAttribute('required', 'required');
    } else {
        document.getElementById('new-category-name').removeAttribute('required');
    }
});

// Edit and delete document functionality (delegated)
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('edit-doc')) {
        const docId = e.target.dataset.id;
        alert('Edit document functionality will be implemented soon.');
    } else if (e.target.classList.contains('delete-doc')) {
        const docId = e.target.dataset.id;
        if (confirm('Are you sure you want to delete this document requirement?')) {
            alert('Delete document functionality will be implemented soon.');
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?> 