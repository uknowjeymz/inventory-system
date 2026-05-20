<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $query = "INSERT INTO all_in_ones (description, serial_number, peripherals, status) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['description'], $_POST['serial_number'], $_POST['peripherals'], $_POST['status']]);
                $success = "All-in-One added successfully!";
                break;
                
            case 'edit':
                $query = "UPDATE all_in_ones SET description = ?, serial_number = ?, peripherals = ?, status = ?, assigned_to = ? WHERE id = ?";
                $assigned_to = $_POST['assigned_to'] == '' ? null : $_POST['assigned_to'];
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['description'], $_POST['serial_number'], $_POST['peripherals'], $_POST['status'], $assigned_to, $_POST['id']]);
                $success = "All-in-One updated successfully!";
                break;
                
            case 'delete':
                $query = "DELETE FROM all_in_ones WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_POST['id']]);
                $success = "All-in-One deleted successfully!";
                break;
        }
    }
}

// Get all all-in-ones with assigned user info
$query = "SELECT a.*, u.full_name as assigned_user FROM all_in_ones a 
          LEFT JOIN users u ON a.assigned_to = u.id 
          ORDER BY a.id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_in_ones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for assignment dropdown
$query = "SELECT id, full_name FROM users WHERE role = 'user'";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "All-in-One Computers Management";
include '../includes/header.php';
?>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Modern Header Section -->
<div class="all-in-ones-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="page-title-section">
                <h2 class="page-title mb-2">
                    <i class="fas fa-desktop text-primary me-3"></i>
                    All-in-One Systems
                </h2>
                <p class="page-subtitle text-muted mb-0">Manage all-in-one computer systems and peripherals</p>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary btn-lg modern-btn" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Add New All-in-One
            </button>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="row mb-4">
    <?php
    $total_aio = count($all_in_ones);
    $available_count = count(array_filter($all_in_ones, function($a) { return $a['status'] == 'available'; }));
    $assigned_count = count(array_filter($all_in_ones, function($a) { return $a['status'] == 'assigned'; }));
    $maintenance_count = count(array_filter($all_in_ones, function($a) { return $a['status'] == 'maintenance'; }));
    ?>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary">
                <i class="fas fa-tv"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_aio; ?></h3>
                <p>Total All-in-Ones</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $available_count; ?></h3>
                <p>Available</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $assigned_count; ?></h3>
                <p>Assigned</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning">
                <i class="fas fa-tools"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $maintenance_count; ?></h3>
                <p>Maintenance</p>
            </div>
        </div>
    </div>
</div>

<!-- Modern List Container -->
<div class="modern-list-container">
    <div class="modern-list-header">
        <h3 class="modern-list-title">
            <i class="fas fa-list me-2"></i>
            All-in-One PCs
        </h3>
        <div class="modern-list-actions">
            <span class="badge badge-primary-modern">
                <?php echo count($all_in_ones); ?> Total Systems
            </span>
        </div>
    </div>
    
    <div class="modern-list-wrapper">
        <?php if (empty($all_in_ones)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-desktop"></i>
            </div>
            <h4>No All-in-One PCs Found</h4>
            <p class="text-muted">No all-in-one systems found in the inventory.</p>
        </div>
        <?php else: ?>
        <div class="list-group modern-list-group">
            <?php foreach ($all_in_ones as $aio): ?>
            <div class="list-group-item modern-list-item">
                <div class="list-item-content">
                    <!-- Single Row Layout -->
                    <div class="list-item-main">
                        <div class="list-item-icon">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <div class="list-item-details">
                            <div class="list-item-title">
                                <h5 class="mb-1">
                                    <?php echo htmlspecialchars($aio['description']); ?> - ID: <?php echo $aio['id']; ?>
                                </h5>
                            </div>
                            <div class="list-item-meta">
                                <span class="badge badge-success-modern me-2">
                                    <i class="fas fa-desktop me-1"></i>
                                    All-in-One
                                </span>
                                <span class="text-muted me-3">
                                    <i class="fas fa-barcode me-1"></i>
                                    <?php echo htmlspecialchars($aio['serial_number']); ?>
                                </span>
                                <span class="text-muted me-3">
                                    <i class="fas fa-keyboard me-1"></i>
                                    <?php echo $aio['peripherals'] ? htmlspecialchars($aio['peripherals']) : 'No peripherals'; ?>
                                </span>
                                <?php if ($aio['assigned_user']): ?>
                                <span class="text-success me-3">
                                    <i class="fas fa-user-check me-1"></i>
                                    <?php echo htmlspecialchars($aio['assigned_user']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted me-3">
                                    <i class="fas fa-user-slash me-1"></i>
                                    Not assigned
                                </span>
                                <?php endif; ?>
                                <!-- Status Badge Inline -->
                                <span class="badge badge-<?php 
                                    echo $aio['status'] == 'available' ? 'success' : 
                                        ($aio['status'] == 'assigned' ? 'primary' : 
                                        ($aio['status'] == 'maintenance' ? 'warning' : 'danger')); 
                                ?>-modern me-3">
                                    <i class="fas fa-<?php 
                                        echo $aio['status'] == 'available' ? 'check-circle' : 
                                            ($aio['status'] == 'assigned' ? 'user' : 
                                            ($aio['status'] == 'maintenance' ? 'tools' : 'exclamation-triangle')); 
                                    ?> me-1"></i>
                                    <?php echo ucfirst($aio['status']); ?>
                                </span>
                                <!-- Action Buttons Inline -->
                                <button class="btn btn-sm btn-outline-primary edit-btn me-1" 
                                        data-id="<?php echo $aio['id']; ?>"
                                        data-description="<?php echo htmlspecialchars($aio['description']); ?>"
                                        data-serial="<?php echo htmlspecialchars($aio['serial_number']); ?>"
                                        data-peripherals="<?php echo htmlspecialchars($aio['peripherals']); ?>"
                                        data-status="<?php echo $aio['status']; ?>"
                                        data-assigned="<?php echo $aio['assigned_to']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        title="Edit All-in-One">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn" 
                                        data-id="<?php echo $aio['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        title="Delete All-in-One">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New All-in-One Computer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peripherals</label>
                        <textarea class="form-control" name="peripherals"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add All-in-One</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit All-in-One Computer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number" id="edit_serial" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peripherals</label>
                        <textarea class="form-control" name="peripherals" id="edit_peripherals"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status" required>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign To User</label>
                        <select class="form-select" name="assigned_to" id="edit_assigned">
                            <option value="">Not Assigned</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update All-in-One</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete All-in-One Computer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <p>Are you sure you want to delete this all-in-one computer? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.edit-btn').click(function() {
        $('#edit_id').val($(this).data('id'));
        $('#edit_description').val($(this).data('description'));
        $('#edit_serial').val($(this).data('serial'));
        $('#edit_peripherals').val($(this).data('peripherals'));
        $('#edit_status').val($(this).data('status'));
        $('#edit_assigned').val($(this).data('assigned'));
    });
    
    $('.delete-btn').click(function() {
        $('#delete_id').val($(this).data('id'));
    });
});
</script>

<?php include '../includes/footer.php'; ?>