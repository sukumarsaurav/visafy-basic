<?php
$page_title = "Manage Documents";
$page_specific_js = "assets/js/documents.js";
$page_specific_css = "assets/css/documents.css";
require_once 'includes/header.php';
?>

<div class="content">
    <h1>Documents Management</h1>
    <p>Manage document categories and document types for visa applications.</p>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2>Document Categories</h2>
                    <button type="button" class="btn btn-primary btn-sm" id="add-category-btn">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                </div>
                <div class="card-body">
                    <ul class="list-group" id="category-list">
                        <?php
                        // Fetch document categories
                        $stmt = $conn->prepare("SELECT id, name, description FROM document_categories ORDER BY name");
                        $stmt->execute();
                        $categories = $stmt->get_result();
                        
                        while ($category = $categories->fetch_assoc()) {
                            echo "<li class='list-group-item category-item' data-id='{$category['id']}' data-name='" . htmlspecialchars($category['name']) . "'>";
                            echo "<div class='d-flex justify-content-between align-items-center'>";
                            echo "<div>";
                            echo "<strong>" . htmlspecialchars($category['name']) . "</strong>";
                            if (!empty($category['description'])) {
                                echo "<p class='text-muted small mb-0'>" . htmlspecialchars($category['description']) . "</p>";
                            }
                            echo "</div>";
                            echo "<div class='btn-group'>";
                            echo "<button class='btn btn-sm btn-outline-primary edit-category-btn' data-id='{$category['id']}' title='Edit'><i class='fas fa-edit'></i></button>";
                            echo "</div>";
                            echo "</div>";
                            echo "</li>";
                        }
                        
                        if ($categories->num_rows == 0) {
                            echo "<li class='list-group-item text-center text-muted'>No categories found</li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2>Document Types <span id="selected-category"></span></h2>
                    <button type="button" class="btn btn-primary btn-sm" id="add-document-btn" style="display: none;">
                        <i class="fas fa-plus"></i> Add Document Type
                    </button>
                </div>
                <div class="card-body">
                    <div id="document-list-placeholder" class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x mb-3 text-muted"></i>
                        <p>Select a category to view document types</p>
                    </div>
                    
                    <div id="document-list-container" style="display: none;">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="document-list"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding/editing document category -->
<div class="modal fade" id="category-modal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="category-form">
                <div class="modal-body">
                    <input type="hidden" id="category-id" name="category_id" value="">
                    
                    <div class="mb-3">
                        <label for="category-name" class="form-label">Category Name*</label>
                        <input type="text" class="form-control" id="category-name" name="category_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category-description" class="form-label">Description</label>
                        <textarea class="form-control" id="category-description" name="category_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for adding/editing document type -->
<div class="modal fade" id="document-modal" tabindex="-1" aria-labelledby="documentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentModalLabel">Add New Document Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="document-form">
                <div class="modal-body">
                    <input type="hidden" id="document-id" name="document_id" value="">
                    <input type="hidden" id="document-category-id" name="category_id" value="">
                    
                    <div class="mb-3">
                        <label for="document-name" class="form-label">Document Name*</label>
                        <input type="text" class="form-control" id="document-name" name="document_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document-description" class="form-label">Description</label>
                        <textarea class="form-control" id="document-description" name="document_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="document-active" name="is_active" checked>
                        <label class="form-check-label" for="document-active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 