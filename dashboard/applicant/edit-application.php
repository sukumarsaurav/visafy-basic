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

// Initialize variables
$errors = [];
$success = false;

// Fetch application details
$stmt = $conn->prepare("
    SELECT a.*, vt.name as visa_type_name
    FROM applications a
    JOIN visa_types vt ON a.visa_type_id = vt.id
    WHERE a.id = ? AND a.user_id = ? AND a.status = 'draft'
");
$stmt->bind_param("ii", $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: applications.php");
    exit();
}

$application = $result->fetch_assoc();

// Fetch visa types
$visa_types = $conn->query("SELECT id, name FROM visa_types WHERE status = 'active' ORDER BY name");

// Fetch document types for the selected visa type
$stmt = $conn->prepare("
    SELECT dt.* 
    FROM document_types dt
    JOIN visa_type_documents vtd ON dt.id = vtd.document_type_id
    WHERE vtd.visa_type_id = ?
    ORDER BY dt.name
");
$stmt->bind_param("i", $application['visa_type_id']);
$stmt->execute();
$document_types = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch existing documents
$stmt = $conn->prepare("
    SELECT ad.*, dt.name as document_type_name
    FROM application_documents ad
    JOIN document_types dt ON ad.document_type_id = dt.id
    WHERE ad.application_id = ?
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$existing_documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate visa type
    $visa_type_id = isset($_POST['visa_type_id']) ? (int)$_POST['visa_type_id'] : 0;
    if ($visa_type_id <= 0) {
        $errors[] = "Please select a visa type";
    }
    
    // Validate travel dates
    $travel_date = isset($_POST['travel_date']) ? trim($_POST['travel_date']) : '';
    if (empty($travel_date)) {
        $errors[] = "Please select your intended travel date";
    } elseif (strtotime($travel_date) < strtotime('today')) {
        $errors[] = "Travel date cannot be in the past";
    }
    
    // Validate purpose of travel
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    if (empty($purpose)) {
        $errors[] = "Please provide the purpose of your travel";
    }
    
    // If no errors, update application
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE applications 
            SET visa_type_id = ?, travel_date = ?, purpose = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ? AND status = 'draft'
        ");
        $stmt->bind_param("issii", $visa_type_id, $travel_date, $purpose, $application_id, $user_id);
        
        if ($stmt->execute()) {
            $success = true;
            
            // Handle document uploads
            foreach ($_FILES['documents']['name'] as $key => $name) {
                if (!empty($name)) {
                    $document_type_id = (int)$_POST['document_type_id'][$key];
                    $file = $_FILES['documents']['tmp_name'][$key];
                    $file_size = $_FILES['documents']['size'][$key];
                    $file_type = $_FILES['documents']['type'][$key];
                    
                    // Validate file
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    if (!in_array($file_type, $allowed_types)) {
                        $errors[] = "Invalid file type for document: " . htmlspecialchars($name);
                        continue;
                    }
                    
                    if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                        $errors[] = "File too large for document: " . htmlspecialchars($name);
                        continue;
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $extension;
                    $upload_path = '../../uploads/documents/' . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file, $upload_path)) {
                        // Delete existing document if any
                        $stmt = $conn->prepare("
                            DELETE FROM application_documents 
                            WHERE application_id = ? AND document_type_id = ?
                        ");
                        $stmt->bind_param("ii", $application_id, $document_type_id);
                        $stmt->execute();
                        
                        // Insert new document
                        $stmt = $conn->prepare("
                            INSERT INTO application_documents 
                            (application_id, document_type_id, file_path, status, created_at)
                            VALUES (?, ?, ?, 'pending', NOW())
                        ");
                        $stmt->bind_param("iis", $application_id, $document_type_id, $filename);
                        $stmt->execute();
                    } else {
                        $errors[] = "Failed to upload document: " . htmlspecialchars($name);
                    }
                }
            }
            
            // Redirect on success
            if (empty($errors)) {
                header("Location: view-application.php?id=" . $application_id);
                exit();
            }
        } else {
            $errors[] = "Failed to update application";
        }
    }
}

// Get notifications
$notifications = getNotifications($user_id);
$unread_notifications = array_filter($notifications, function($notification) {
    return $notification['is_read'] == 0;
});
$unread_count = count($unread_notifications);

// Set page title
$page_title = "Edit Application - " . $application['visa_type_name'];
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
                    <h1 class="h2">Edit Application</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="view-application.php?id=<?php echo $application_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Application
                        </a>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Application updated successfully.
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <!-- Visa Type -->
                            <div class="mb-3">
                                <label for="visa_type_id" class="form-label">Visa Type</label>
                                <select class="form-select" id="visa_type_id" name="visa_type_id" required>
                                    <option value="">Select Visa Type</option>
                                    <?php while ($visa_type = $visa_types->fetch_assoc()): ?>
                                        <option value="<?php echo $visa_type['id']; ?>" <?php echo $visa_type['id'] == $application['visa_type_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($visa_type['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <!-- Travel Date -->
                            <div class="mb-3">
                                <label for="travel_date" class="form-label">Intended Travel Date</label>
                                <input type="date" class="form-control" id="travel_date" name="travel_date" 
                                       value="<?php echo $application['travel_date']; ?>" required>
                            </div>
                            
                            <!-- Purpose -->
                            <div class="mb-3">
                                <label for="purpose" class="form-label">Purpose of Travel</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="3" required><?php echo htmlspecialchars($application['purpose']); ?></textarea>
                            </div>
                            
                            <!-- Documents -->
                            <div class="mb-3">
                                <h5>Documents</h5>
                                <p class="text-muted">Please upload all required documents. Maximum file size: 5MB. Allowed formats: PDF, JPEG, PNG</p>
                                
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Document Type</th>
                                                <th>Current File</th>
                                                <th>Upload New File</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($document_types as $doc_type): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($doc_type['name']); ?>
                                                        <input type="hidden" name="document_type_id[]" value="<?php echo $doc_type['id']; ?>">
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $current_doc = array_filter($existing_documents, function($doc) use ($doc_type) {
                                                            return $doc['document_type_id'] == $doc_type['id'];
                                                        });
                                                        $current_doc = reset($current_doc);
                                                        if ($current_doc): ?>
                                                            <a href="<?php echo htmlspecialchars($current_doc['file_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-download"></i> Download
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">No file uploaded</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <input type="file" class="form-control" name="documents[]" accept=".pdf,.jpg,.jpeg,.png">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
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