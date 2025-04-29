<?php
$page_title = "Task Management";
$page_specific_css = "assets/css/tasks.css";
require_once 'includes/header.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the base query
$query = "SELECT t.*, 
          CONCAT(u.first_name, ' ', u.last_name) as admin_name,
          COUNT(DISTINCT ta.id) as assignment_count,
          COUNT(DISTINCT tc.id) as comment_count,
          COUNT(DISTINCT att.id) as attachment_count
          FROM tasks t
          LEFT JOIN users u ON t.admin_id = u.id
          LEFT JOIN task_assignments ta ON t.id = ta.task_id
          LEFT JOIN task_comments tc ON t.id = tc.task_id
          LEFT JOIN task_attachments att ON t.id = att.task_id
          WHERE t.deleted_at IS NULL";

// Add filters
if ($status_filter !== 'all') {
    $query .= " AND t.status = ?";
}

if ($priority_filter !== 'all') {
    $query .= " AND t.priority = ?";
}

if (!empty($date_from)) {
    $query .= " AND t.due_date >= ?";
}

if (!empty($date_to)) {
    $query .= " AND t.due_date <= ?";
}

if (!empty($search)) {
    $query .= " AND (t.name LIKE ? OR t.description LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
}

$query .= " GROUP BY t.id ORDER BY 
            CASE 
                WHEN t.status = 'pending' THEN 1
                WHEN t.status = 'in_progress' THEN 2
                WHEN t.status = 'completed' THEN 3
                WHEN t.status = 'cancelled' THEN 4
            END,
            CASE 
                WHEN t.priority = 'high' THEN 1
                WHEN t.priority = 'normal' THEN 2
                WHEN t.priority = 'low' THEN 3
            END,
            t.due_date ASC";

// Prepare and execute the statement
$stmt = $conn->prepare($query);

// Bind parameters
$paramTypes = '';
$paramValues = [];

if ($status_filter !== 'all') {
    $paramTypes .= 's';
    $paramValues[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $paramTypes .= 's';
    $paramValues[] = $priority_filter;
}

if (!empty($date_from)) {
    $paramTypes .= 's';
    $paramValues[] = $date_from;
}

if (!empty($date_to)) {
    $paramTypes .= 's';
    $paramValues[] = $date_to;
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
$tasks = $result->fetch_all(MYSQLI_ASSOC);

// Get team members for assignment dropdown
$team_members_query = "SELECT tm.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.email
                      FROM team_members tm
                      JOIN users u ON tm.user_id = u.id
                      WHERE u.status = 'active'
                      ORDER BY u.first_name, u.last_name";
$team_members_result = $conn->query($team_members_query);
$team_members = $team_members_result->fetch_all(MYSQLI_ASSOC);

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $task_name = $_POST['task_name'];
    $task_description = $_POST['task_description'];
    $task_priority = $_POST['task_priority'];
    $task_due_date = !empty($_POST['task_due_date']) ? $_POST['task_due_date'] : NULL;
    $assigned_members = isset($_POST['assigned_members']) ? $_POST['assigned_members'] : [];
    
    // Validate inputs
    if (empty($task_name)) {
        $error_message = "Task name is required";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert task
            $task_query = "INSERT INTO tasks (name, description, priority, admin_id, due_date) 
                          VALUES (?, ?, ?, ?, ?)";
            
            $task_stmt = $conn->prepare($task_query);
            $admin_id = $_SESSION['id'];
            $task_stmt->bind_param('sssis', $task_name, $task_description, $task_priority, $admin_id, $task_due_date);
            $task_stmt->execute();
            
            $task_id = $conn->insert_id;
            
            // Assign team members
            if (!empty($assigned_members)) {
                $assign_query = "INSERT INTO task_assignments (task_id, team_member_id) VALUES (?, ?)";
                $assign_stmt = $conn->prepare($assign_query);
                
                foreach ($assigned_members as $member_id) {
                    $assign_stmt->bind_param('ii', $task_id, $member_id);
                    $assign_stmt->execute();
                    
                    // Log activity
                    $activity_query = "INSERT INTO task_activity_logs 
                                     (task_id, user_id, team_member_id, activity_type, description) 
                                     VALUES (?, ?, ?, 'assigned', ?)";
                    
                    $description = "Assigned team member ID $member_id to the task";
                    $activity_stmt = $conn->prepare($activity_query);
                    $activity_stmt->bind_param('iiis', $task_id, $admin_id, $member_id, $description);
                    $activity_stmt->execute();
                }
            }
            
            // Log task creation
            $activity_query = "INSERT INTO task_activity_logs 
                             (task_id, user_id, activity_type, description) 
                             VALUES (?, ?, 'created', ?)";
            
            $description = "Task created";
            $activity_stmt = $conn->prepare($activity_query);
            $activity_stmt->bind_param('iis', $task_id, $admin_id, $description);
            $activity_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Task created successfully";
            
            // Refresh task data
            header("Location: tasks.php");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error creating task: " . $e->getMessage();
        }
    }
}

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['new_status'];
    
    $update_query = "UPDATE tasks SET status = ?, 
                    completed_at = " . ($new_status === 'completed' ? 'NOW()' : 'NULL') . " 
                    WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('si', $new_status, $task_id);
    
    if ($update_stmt->execute()) {
        // Log activity
        $activity_query = "INSERT INTO task_activity_logs 
                        (task_id, user_id, activity_type, description) 
                        VALUES (?, ?, 'status_changed', ?)";
        
        $admin_id = $_SESSION['id'];
        $description = "Task status changed to $new_status";
        $activity_stmt = $conn->prepare($activity_query);
        $activity_stmt->bind_param('iis', $task_id, $admin_id, $description);
        $activity_stmt->execute();
        
        $success_message = "Task status updated successfully";
        
        // Refresh task data
        header("Location: tasks.php?status=$status_filter");
        exit;
    } else {
        $error_message = "Error updating status: " . $conn->error;
    }
}

// Handle task assignment update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    $task_id = $_POST['task_id'];
    $assigned_members = isset($_POST['assigned_members']) ? $_POST['assigned_members'] : [];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get current assignments
        $current_query = "SELECT team_member_id FROM task_assignments WHERE task_id = ?";
        $current_stmt = $conn->prepare($current_query);
        $current_stmt->bind_param('i', $task_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        
        $current_assignments = [];
        while ($row = $current_result->fetch_assoc()) {
            $current_assignments[] = $row['team_member_id'];
        }
        
        // Determine members to add and remove
        $to_add = array_diff($assigned_members, $current_assignments);
        $to_remove = array_diff($current_assignments, $assigned_members);
        
        // Add new assignments
        if (!empty($to_add)) {
            $add_query = "INSERT INTO task_assignments (task_id, team_member_id) VALUES (?, ?)";
            $add_stmt = $conn->prepare($add_query);
            
            foreach ($to_add as $member_id) {
                $add_stmt->bind_param('ii', $task_id, $member_id);
                $add_stmt->execute();
                
                // Log activity
                $activity_query = "INSERT INTO task_activity_logs 
                                 (task_id, user_id, team_member_id, activity_type, description) 
                                 VALUES (?, ?, ?, 'assigned', ?)";
                
                $admin_id = $_SESSION['id'];
                $description = "Assigned team member ID $member_id to the task";
                $activity_stmt = $conn->prepare($activity_query);
                $activity_stmt->bind_param('iiis', $task_id, $admin_id, $member_id, $description);
                $activity_stmt->execute();
            }
        }
        
        // Remove assignments
        if (!empty($to_remove)) {
            $remove_query = "DELETE FROM task_assignments WHERE task_id = ? AND team_member_id = ?";
            $remove_stmt = $conn->prepare($remove_query);
            
            foreach ($to_remove as $member_id) {
                $remove_stmt->bind_param('ii', $task_id, $member_id);
                $remove_stmt->execute();
                
                // Log activity
                $activity_query = "INSERT INTO task_activity_logs 
                                 (task_id, user_id, team_member_id, activity_type, description) 
                                 VALUES (?, ?, ?, 'unassigned', ?)";
                
                $admin_id = $_SESSION['id'];
                $description = "Removed team member ID $member_id from the task";
                $activity_stmt = $conn->prepare($activity_query);
                $activity_stmt->bind_param('iiis', $task_id, $admin_id, $member_id, $description);
                $activity_stmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Task assignments updated successfully";
        
        // Refresh task data
        header("Location: tasks.php?status=$status_filter");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error updating assignments: " . $e->getMessage();
    }
}
?>

<div class="content">
    <h1>Task Management</h1>
    <p>Create, assign, and track tasks for your team members.</p>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Filters Section -->
    <div class="section">
        <h2>Filter Tasks</h2>
        <div class="filter-card">
            <form action="tasks.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select name="priority" id="priority" class="form-control">
                        <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="normal" <?php echo $priority_filter === 'normal' ? 'selected' : ''; ?>>Normal</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_from">Due Date From</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">Due Date To</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Task name, description, creator..." value="<?php echo $search; ?>">
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn filter-btn">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="tasks.php" class="btn reset-btn">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Task Section -->
    <div class="section">
        <h2>Create New Task</h2>
        <div class="task-form-card">
            <form action="tasks.php" method="POST" class="create-task-form">
                <div class="form-group">
                    <label for="task_name">Task Name*</label>
                    <input type="text" name="task_name" id="task_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="task_description">Description</label>
                    <textarea name="task_description" id="task_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="task_priority">Priority</label>
                        <select name="task_priority" id="task_priority" class="form-control">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="task_due_date">Due Date</label>
                        <input type="datetime-local" name="task_due_date" id="task_due_date" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label for="assigned_members">Assign To Team Members</label>
                    <select name="assigned_members[]" id="assigned_members" class="form-control" multiple>
                        <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['name']); ?> (<?php echo htmlspecialchars($member['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Hold Ctrl/Cmd to select multiple team members</small>
                </div>
                <div class="form-buttons">
                    <button type="submit" name="create_task" class="btn submit-btn">Create Task</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tasks Section -->
    <div class="section">
        <h2>Tasks</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Task Name</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Assignees</th>
                        <th>Comments</th>
                        <th>Files</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tasks) > 0): ?>
                        <?php foreach ($tasks as $task): ?>
                            <tr class="task-row status-<?php echo $task['status']; ?> priority-<?php echo $task['priority']; ?>">
                                <td><?php echo htmlspecialchars($task['name']); ?></td>
                                <td>
                                    <span class="badge priority-<?php echo $task['priority']; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $task['status']; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($task['due_date']): ?>
                                        <?php 
                                            $due_date = new DateTime($task['due_date']);
                                            $now = new DateTime();
                                            $interval = $now->diff($due_date);
                                            $is_overdue = $due_date < $now && $task['status'] !== 'completed' && $task['status'] !== 'cancelled';
                                        ?>
                                        <span class="<?php echo $is_overdue ? 'overdue' : ''; ?>">
                                            <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                            <?php if ($is_overdue): ?>
                                                <br><span class="overdue-tag">Overdue</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge no-date">No Due Date</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        // Get assignee count
                                        $assignee_query = "SELECT COUNT(*) as count FROM task_assignments WHERE task_id = ?";
                                        $assignee_stmt = $conn->prepare($assignee_query);
                                        $assignee_stmt->bind_param('i', $task['id']);
                                        $assignee_stmt->execute();
                                        $assignee_count = $assignee_stmt->get_result()->fetch_assoc()['count'];
                                    ?>
                                    <span class="badge count-badge"><?php echo $assignee_count; ?></span>
                                </td>
                                <td>
                                    <span class="badge count-badge"><?php echo $task['comment_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge count-badge"><?php echo $task['attachment_count']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($task['admin_name']); ?></td>
                                <td class="actions">
                                    <button class="action-btn view-btn" onclick="location.href='view_task.php?id=<?php echo $task['id']; ?>'">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn assign-btn" data-task-id="<?php echo $task['id']; ?>"
                                        data-task-name="<?php echo htmlspecialchars($task['name']); ?>">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                    <button class="action-btn status-btn" 
                                        data-task-id="<?php echo $task['id']; ?>"
                                        data-task-name="<?php echo htmlspecialchars($task['name']); ?>"
                                        data-current-status="<?php echo $task['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn comment-btn" onclick="location.href='view_task.php?id=<?php echo $task['id']; ?>#comments'">
                                        <i class="fas fa-comment"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-data">No tasks found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for updating task status -->
<div class="modal" id="status-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Update Task Status</h2>
        <form id="update-status-form" method="POST" action="tasks.php">
            <input type="hidden" name="task_id" id="status-task-id">
            
            <div class="form-group">
                <label>Task Name: <span id="status-task-name"></span></label>
            </div>
            
            <div class="form-group">
                <label for="new_status">New Status*</label>
                <select name="new_status" id="new_status" class="form-control" required>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-buttons">
                <button type="button" class="btn cancel-btn" id="cancel-status">Cancel</button>
                <button type="submit" name="update_status" class="btn submit-btn">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal for assigning team members -->
<div class="modal" id="assign-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Assign Team Members</h2>
        <form id="assign-task-form" method="POST" action="tasks.php">
            <input type="hidden" name="task_id" id="modal-task-id">
            
            <div class="form-group">
                <label>Task Name: <span id="modal-task-name"></span></label>
            </div>
            
            <div class="form-group">
                <label for="assigned_members_modal">Select Team Members*</label>
                <select name="assigned_members[]" id="assigned_members_modal" class="form-control" multiple required>
                    <?php foreach ($team_members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['name']); ?> (<?php echo htmlspecialchars($member['email']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Hold Ctrl/Cmd to select multiple team members</small>
            </div>
            
            <div class="form-buttons">
                <button type="button" class="btn cancel-btn" id="cancel-assign">Cancel</button>
                <button type="submit" name="update_assignment" class="btn submit-btn">Update Assignments</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functionality
const statusModal = document.getElementById('status-modal');
const assignModal = document.getElementById('assign-modal');
const closeButtons = document.querySelectorAll('.close');
const cancelStatusBtn = document.getElementById('cancel-status');
const cancelAssignBtn = document.getElementById('cancel-assign');

// Open status modal
document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const taskId = this.dataset.taskId;
        const taskName = this.dataset.taskName;
        const currentStatus = this.dataset.currentStatus;
        
        document.getElementById('status-task-id').value = taskId;
        document.getElementById('status-task-name').textContent = taskName;
        document.getElementById('new_status').value = currentStatus;
        
        statusModal.style.display = 'block';
    });
});

// Open assign modal
document.querySelectorAll('.assign-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const taskId = this.dataset.taskId;
        const taskName = this.dataset.taskName;
        
        document.getElementById('modal-task-id').value = taskId;
        document.getElementById('modal-task-name').textContent = taskName;
        
        // Fetch current assignments for this task
        fetch('ajax/get_task_assignments.php?task_id=' + taskId)
            .then(response => response.json())
            .then(data => {
                const selectElement = document.getElementById('assigned_members_modal');
                
                // Clear previous selections
                Array.from(selectElement.options).forEach(option => {
                    option.selected = false;
                });
                
                // Set selected options based on current assignments
                if (data.success) {
                    data.assignments.forEach(assignmentId => {
                        Array.from(selectElement.options).forEach(option => {
                            if (option.value == assignmentId) {
                                option.selected = true;
                            }
                        });
                    });
                }
                
                assignModal.style.display = 'block';
            })
            .catch(error => {
                console.error('Error fetching assignments:', error);
                // Still show the modal even if fetch fails
                assignModal.style.display = 'block';
            });
    });
});

// Close buttons functionality
closeButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Cancel buttons
if (cancelStatusBtn) {
    cancelStatusBtn.addEventListener('click', function() {
        statusModal.style.display = 'none';
    });
}

if (cancelAssignBtn) {
    cancelAssignBtn.addEventListener('click', function() {
        assignModal.style.display = 'none';
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === statusModal) {
        statusModal.style.display = 'none';
    }
    if (event.target === assignModal) {
        assignModal.style.display = 'none';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
